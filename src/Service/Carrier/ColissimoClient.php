<?php

namespace App\Service\Carrier;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client pour l'API Colissimo (La Poste).
 *
 * Génération d'étiquettes : SOAP/MTOM via https://ws.colissimo.fr/sls-ws/SlsServiceWS
 *   → L'API retourne une réponse multipart/related (MTOM) avec le PDF en pièce jointe binaire.
 *   → PHP SoapClient ne supporte pas MTOM ; on envoie la requête SOAP via HttpClient
 *     et on parse le multipart manuellement.
 *
 * Widget token : REST via https://ws.colissimo.fr/widget-colissimo/rest/authenticate.rest
 * Suivi colis  : REST via https://ws.colissimo.fr/tracking-cxf/rest/v2/consolidated/search
 *
 * Documentation : https://www.colissimo.entreprise.laposte.fr/fr/developpeurs
 */
final class ColissimoClient
{
    private const SOAP_URL = 'https://ws.colissimo.fr/sls-ws/SlsServiceWS';

    private readonly HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly string $login,
        private readonly string $password,
        private readonly string $senderName,
        private readonly string $senderStreet = '',
        private readonly string $senderZip    = '',
        private readonly string $senderCity   = '',
    ) {
        // Le token widget est récupéré en synchrone pendant le checkout :
        // sans borne, une API Colissimo dégradée bloque la page jusqu'au
        // timeout socket (~60 s). timeout = inactivité, max_duration = total.
        $this->httpClient = $httpClient->withOptions([
            'timeout' => 5,
            'max_duration' => 15,
        ]);
    }

    // ── Label generation ─────────────────────────────────────────────────────

    /**
     * Génère une étiquette Colissimo pour une livraison à domicile.
     *
     * @param array{
     *   weight: float,
     *   recipient: array{lastName: string, firstName: string, line2: string, countryCode: string, city: string, zipCode: string},
     *   orderReference: string,
     * } $params
     *
     * @return array{trackingNumber: string, labelBase64: string}
     * @throws \RuntimeException
     */
    public function generateLabel(array $params): array
    {
        $xml = $this->buildGenerateLabelXml(
            productCode:    'DOM',
            weight:         $params['weight'],
            orderReference: $params['orderReference'],
            recipient:      $params['recipient'],
        );

        return $this->callGenerateLabel($xml, 'generateLabel');
    }

    /**
     * Génère une étiquette Colissimo pour une livraison en point relais.
     *
     * @param array{
     *   weight: float,
     *   pickupPointId: string,
     *   recipient: array{lastName: string, firstName: string, line2: string, countryCode: string, city: string, postalCode: string},
     *   orderReference: string,
     * } $params
     *
     * @return array{trackingNumber: string, labelBase64: string}
     * @throws \RuntimeException
     */
    public function generateLabelPickupPoint(array $params): array
    {
        $xml = $this->buildGenerateLabelXml(
            productCode:    'A2P',
            weight:         $params['weight'],
            orderReference: $params['orderReference'],
            recipient:      array_merge($params['recipient'], ['zipCode' => $params['recipient']['postalCode']]),
            pickupPointId:  $params['pickupPointId'],
        );

        return $this->callGenerateLabel($xml, 'generateLabelPickupPoint');
    }

    // ── Widget token (REST) ──────────────────────────────────────────────────

    /**
     * Obtient un token JWT pour le widget Colissimo point retrait (valide ~28 min).
     *
     * @throws \RuntimeException
     */
    /**
     * Retourne le token d'authentification du widget Colissimo.
     * Le token est mis en cache 25 minutes (Colissimo l'invalide après ~28 min).
     */
    public function getWidgetToken(): string
    {
        return $this->cache->get('colissimo_widget_token', function (ItemInterface $item): string {
            $item->expiresAfter(1500); // 25 minutes

            try {
                $response = $this->httpClient->request('POST', 'https://ws.colissimo.fr/widget-colissimo/rest/authenticate.rest', [
                    'json' => [
                        'login'    => $this->login,
                        'password' => $this->password,
                    ],
                ]);

                $data = $response->toArray(false);
            } catch (\Throwable $e) {
                $this->logger->error('[Colissimo] Erreur authentification widget', ['exception' => $e->getMessage()]);
                throw new \RuntimeException('Colissimo widget auth failed: ' . $e->getMessage(), 0, $e);
            }

            $token = $data['token'] ?? null;
            if (!$token) {
                throw new \RuntimeException('Colissimo widget: token absent de la réponse.');
            }

            return $token;
        });
    }

    // ── Tracking (REST) ──────────────────────────────────────────────────────

    public function getTrackingUrl(string $trackingNumber): string
    {
        return \sprintf(
            'https://www.laposte.fr/outils/suivre-vos-envois?code=%s',
            urlencode($trackingNumber)
        );
    }

    /**
     * Récupère l'historique de suivi d'un colis.
     *
     * @return array{statusCode: string, label: string, events: list<array{code: string, label: string, date: string|null, site: string|null, rawData: array}>}
     * @throws \RuntimeException
     */
    public function getTracking(string $trackingNumber): array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://ws.colissimo.fr/tracking-cxf/rest/v2/consolidated/search', [
                'query' => [
                    'login'         => $this->login,
                    'password'      => $this->password,
                    'skybillNumber' => $trackingNumber,
                    'lang'          => 'FR',
                ],
            ]);

            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger->error('[Colissimo] Erreur getTracking', ['tracking' => $trackingNumber, 'exception' => $e->getMessage()]);
            throw new \RuntimeException('Colissimo tracking API unreachable: ' . $e->getMessage(), 0, $e);
        }

        $errorCode = $data['errorCode'] ?? null;
        if ($errorCode && $errorCode !== '000') {
            throw new \RuntimeException(\sprintf('Colissimo tracking error %s: %s', $errorCode, $data['errorMessage'] ?? 'unknown'));
        }

        $shipment    = $data['shipment']       ?? [];
        $statusCode  = $shipment['statusCode'] ?? 'UNKNOWN';
        $statusLabel = $shipment['status']     ?? '';

        $events = [];
        foreach ($shipment['eventDetailList'] ?? [] as $raw) {
            $events[] = [
                'code'    => $raw['code']  ?? '',
                'label'   => $raw['label'] ?? '',
                'date'    => $raw['date']  ?? null,
                'site'    => $raw['site']  ?? null,
                'rawData' => $raw,
            ];
        }

        $this->logger->info('[Colissimo] Tracking récupéré', ['tracking' => $trackingNumber, 'statusCode' => $statusCode, 'events' => \count($events)]);

        return ['statusCode' => $statusCode, 'label' => $statusLabel, 'events' => $events];
    }

    // ── Pickup point search (REST) ──────────────────────────────────────────

    /**
     * Recherche les points relais Colissimo par code postal.
     *
     * @return list<array{id:string, name:string, address:string, city:string, postalCode:string, lat:float, lng:float, distance:int, hours:array}>
     * @throws \RuntimeException
     */
    public function searchPickupPoints(string $zipCode, string $city = ''): array
    {
        if ($city === '') {
            $city = $this->resolveCityFromZip($zipCode) ?? '';
        }

        $e    = fn(string $v): string => htmlspecialchars($v, \ENT_XML1, 'UTF-8');
        $date = (new \DateTimeImmutable())->format('d/m/Y');

        $xml = <<<XML
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:poi="http://pointretrait.geopost.com/">
          <soapenv:Body>
            <poi:findRDVPointRetraitAcheminement>
              <accountNumber>{$e($this->login)}</accountNumber>
              <password>{$e($this->password)}</password>
              <address></address>
              <zipCode>{$e($zipCode)}</zipCode>
              <city>{$e($city)}</city>
              <weight>500</weight>
              <shippingDate>{$e($date)}</shippingDate>
              <filterRelay>1</filterRelay>
            </poi:findRDVPointRetraitAcheminement>
          </soapenv:Body>
        </soapenv:Envelope>
        XML;

        try {
            $response = $this->httpClient->request('POST', 'https://ws.colissimo.fr/pointretrait-ws-cxf/PointRetraitServiceWS', [
                'headers' => ['Content-Type' => 'text/xml; charset=utf-8'],
                'body'    => $xml,
            ]);
            $body = $response->getContent(false);
        } catch (\Throwable $e) {
            $this->logger->error('[Colissimo] Erreur recherche points relais', ['exception' => $e->getMessage()]);
            throw new \RuntimeException('Colissimo pickup points search failed: ' . $e->getMessage(), 0, $e);
        }

        // Parse chaque <listePointRetraitAcheminement> du XML
        \preg_match_all(
            '/<listePointRetraitAcheminement>(.*?)<\/listePointRetraitAcheminement>/s',
            $body,
            $matches
        );

        $points = [];
        foreach ($matches[1] as $block) {
            $get = static function (string $tag) use ($block): string {
                \preg_match("/<{$tag}>([^<]*)<\/{$tag}>/", $block, $m);
                return \trim($m[1] ?? '');
            };

            $address = \trim(\implode(' ', \array_filter([$get('adresse1'), $get('adresse2')])));

            $points[] = [
                'id'         => $get('identifiant'),
                'name'       => $get('nom'),
                'address'    => $address,
                'city'       => $get('localite'),
                'postalCode' => $get('codePostal'),
                'lat'        => (float) $get('coordGeolocalisationLatitude'),
                'lng'        => (float) $get('coordGeolocalisationLongitude'),
                'distance'   => (int)   $get('distanceEnMetre'),
                'hours'      => [
                    'lundi'    => $get('horairesOuvertureLundi'),
                    'mardi'    => $get('horairesOuvertureMardi'),
                    'mercredi' => $get('horairesOuvertureMercredi'),
                    'jeudi'    => $get('horairesOuvertureJeudi'),
                    'vendredi' => $get('horairesOuvertureVendredi'),
                    'samedi'   => $get('horairesOuvertureSamedi'),
                    'dimanche' => $get('horairesOuvertureDimanche'),
                ],
            ];
        }

        return $points;
    }

    private function resolveCityFromZip(string $zipCode): ?string
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api-adresse.data.gouv.fr/search/', [
                'query' => ['q' => $zipCode, 'type' => 'municipality', 'limit' => 1],
            ]);
            $data = $response->toArray(false);
            return $data['features'][0]['properties']['city'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ── SOAP/MTOM helpers ────────────────────────────────────────────────────

    private function buildGenerateLabelXml(
        string  $productCode,
        float   $weight,
        string  $orderReference,
        array   $recipient,
        ?string $pickupPointId = null,
    ): string {
        $e    = fn(string $v): string => htmlspecialchars($v, \ENT_XML1, 'UTF-8');
        $date = (new \DateTimeImmutable())->format('Y-m-d');

        $pickupLocationXml = '';
        if ($pickupPointId !== null) {
            $pickupLocationXml = "<pickupLocationId>{$e($pickupPointId)}</pickupLocationId>";
        }

        $mobileXml = '';
        $mobile = $recipient['mobileNumber'] ?? '';
        if ($mobile !== '') {
            $mobileXml = "<mobileNumber>{$e($mobile)}</mobileNumber>";
        }

        $serviceExtra = '';
        if ($pickupPointId !== null) {
            $serviceExtra = "<commercialName>{$e($this->senderName)}</commercialName>"
                . "<returnTypeChoice>2</returnTypeChoice>";
        }

        return <<<XML
        <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:gen="http://sls.ws.coliposte.fr">
          <soapenv:Body>
            <gen:generateLabel>
              <generateLabelRequest>
                <contractNumber>{$e($this->login)}</contractNumber>
                <password>{$e($this->password)}</password>
                <outputFormat>
                  <x>0</x>
                  <y>0</y>
                  <outputPrintingType>PDF_10x15_300dpi</outputPrintingType>
                </outputFormat>
                <letter>
                  <service>
                    <productCode>{$e($productCode)}</productCode>
                    <depositDate>{$e($date)}</depositDate>
                    <orderNumber>{$e($orderReference)}</orderNumber>
                    {$serviceExtra}
                  </service>
                  <parcel>
                    <weight>{$e((string) $weight)}</weight>
                    {$pickupLocationXml}
                  </parcel>
                  <sender>
                    <address>
                      <companyName>{$e($this->senderName)}</companyName>
                      <line2>{$e($this->senderStreet)}</line2>
                      <countryCode>FR</countryCode>
                      <city>{$e($this->senderCity)}</city>
                      <zipCode>{$e($this->senderZip)}</zipCode>
                    </address>
                  </sender>
                  <addressee>
                    <address>
                      <lastName>{$e($recipient['lastName'] ?? '')}</lastName>
                      <firstName>{$e($recipient['firstName'] ?? '')}</firstName>
                      <line2>{$e($recipient['line2'] ?? '')}</line2>
                      <line3>{$e($recipient['line3'] ?? '')}</line3>
                      <countryCode>{$e($recipient['countryCode'] ?? 'FR')}</countryCode>
                      <city>{$e($recipient['city'] ?? '')}</city>
                      <zipCode>{$e($recipient['zipCode'] ?? '')}</zipCode>
                      {$mobileXml}
                    </address>
                  </addressee>
                </letter>
              </generateLabelRequest>
            </gen:generateLabel>
          </soapenv:Body>
        </soapenv:Envelope>
        XML;
    }

    /**
     * Envoie la requête SOAP, parse la réponse MTOM et extrait le numéro de suivi + le PDF base64.
     *
     * @throws \RuntimeException
     */
    private function callGenerateLabel(string $soapXml, string $context): array
    {
        try {
            $response = $this->httpClient->request('POST', self::SOAP_URL, [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction'   => '""',
                ],
                'body' => $soapXml,
            ]);

            $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
            $body        = $response->getContent(false);
        } catch (\Throwable $e) {
            $this->logger->error('[Colissimo] Erreur HTTP ' . $context, ['exception' => $e->getMessage()]);
            throw new \RuntimeException('Colissimo API unreachable: ' . $e->getMessage(), 0, $e);
        }

        // Extraire le XML SOAP depuis la réponse MTOM (multipart/related)
        $soapResponse = $this->extractSoapFromMtom($body, $contentType);

        // Parser le XML de la réponse
        $xml = @simplexml_load_string($soapResponse);
        if ($xml === false) {
            $this->logger->error('[Colissimo] Réponse XML invalide ' . $context, ['body' => substr($body, 0, 500)]);
            throw new \RuntimeException('Colissimo: réponse XML non parsable.');
        }

        // Chercher le nœud messages dans toute la réponse
        $xmlStr = $soapResponse;
        if (preg_match_all('/<(?:\w+:)?messages>(.*?)<\/(?:\w+:)?messages>/s', $xmlStr, $matches)) {
            foreach ($matches[1] as $msgXml) {
                if (str_contains($msgXml, '<type>ERROR</type>')) {
                    preg_match('/<id>(.*?)<\/id>/', $msgXml, $idM);
                    preg_match('/<messageContent>(.*?)<\/messageContent>/', $msgXml, $contentM);
                    $errId  = $idM[1]      ?? '?';
                    $errMsg = $contentM[1] ?? '?';
                    $this->logger->error('[Colissimo] Erreur API ' . $context, ['id' => $errId, 'message' => $errMsg]);
                    throw new \RuntimeException("Colissimo error {$errId}: {$errMsg}");
                }
            }
        }

        // Extraire parcelNumber et label
        preg_match('/<(?:\w+:)?parcelNumber>(.*?)<\/(?:\w+:)?parcelNumber>/', $xmlStr, $pnMatch);
        $trackingNumber = $pnMatch[1] ?? null;

        // Le PDF peut être inline en base64 dans le XML ou en pièce jointe MTOM
        preg_match('/<(?:\w+:)?label>(.*?)<\/(?:\w+:)?label>/s', $xmlStr, $labelMatch);
        $labelBase64 = $labelMatch[1] ?? null;

        // Si le label est une référence XOP (Include href), extraire la pièce jointe binaire
        if ($labelBase64 === null || str_contains((string) $labelBase64, 'xop:Include') || str_contains((string) $labelBase64, 'Include href')) {
            $labelBase64 = $this->extractMtomAttachment($body, $contentType);
        }

        if (!$trackingNumber) {
            $this->logger->error('[Colissimo] parcelNumber manquant ' . $context, ['response' => substr($xmlStr, 0, 2000), 'raw_body' => substr($body, 0, 2000)]);
            throw new \RuntimeException('Colissimo: numéro de suivi absent de la réponse. Réponse API: ' . substr($xmlStr, 0, 500));
        }

        if (!$labelBase64) {
            $this->logger->error('[Colissimo] label PDF manquant ' . $context, ['tracking' => $trackingNumber]);
            throw new \RuntimeException('Colissimo: étiquette PDF absente de la réponse.');
        }

        $this->logger->info('[Colissimo] Étiquette générée', ['context' => $context, 'tracking' => $trackingNumber]);

        return [
            'trackingNumber' => trim($trackingNumber),
            'labelBase64'    => trim($labelBase64),
        ];
    }

    /**
     * Extrait la partie XML SOAP d'une réponse MTOM (multipart/related).
     * Si la réponse n'est pas multipart, la retourne telle quelle.
     */
    private function extractSoapFromMtom(string $body, string $contentType): string
    {
        if (!str_contains($contentType, 'multipart/related')) {
            return $body;
        }

        // Extraire le boundary
        if (!preg_match('/boundary="?([^";]+)"?/i', $contentType, $m)) {
            return $body;
        }

        $boundary = '--' . trim($m[1]);
        $parts    = explode($boundary, $body);

        // La première partie non vide après le boundary est le XML SOAP
        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");
            if ($part === '' || $part === '--' || str_starts_with($part, '--')) {
                continue;
            }

            // Séparer headers et body de la partie
            $separatorPos = strpos($part, "\r\n\r\n");
            if ($separatorPos === false) {
                $separatorPos = strpos($part, "\n\n");
            }

            if ($separatorPos === false) {
                continue;
            }

            $partHeaders = substr($part, 0, $separatorPos);
            $partBody    = substr($part, $separatorPos + 4);

            // La partie XML est celle avec application/xop+xml ou text/xml
            if (str_contains($partHeaders, 'application/xop+xml') || str_contains($partHeaders, 'text/xml')) {
                return trim($partBody);
            }
        }

        return $body;
    }

    /**
     * Extrait la pièce jointe binaire (PDF) d'une réponse MTOM et la retourne en base64.
     */
    private function extractMtomAttachment(string $body, string $contentType): ?string
    {
        if (!str_contains($contentType, 'multipart/related')) {
            return null;
        }

        if (!preg_match('/boundary="?([^";]+)"?/i', $contentType, $m)) {
            return null;
        }

        $boundary = '--' . trim($m[1]);
        $parts    = explode($boundary, $body);

        $xmlFound = false;

        foreach ($parts as $part) {
            $part = ltrim($part, "\r\n");
            if ($part === '' || $part === '--' || str_starts_with($part, '--')) {
                continue;
            }

            $separatorPos = strpos($part, "\r\n\r\n");
            if ($separatorPos === false) {
                $separatorPos = strpos($part, "\n\n");
            }
            if ($separatorPos === false) {
                continue;
            }

            $partHeaders = substr($part, 0, $separatorPos);
            $partBody    = substr($part, $separatorPos + 4);

            // Sauter la première partie (XML SOAP)
            if (!$xmlFound) {
                if (str_contains($partHeaders, 'application/xop+xml') || str_contains($partHeaders, 'text/xml')) {
                    $xmlFound = true;
                }
                continue;
            }

            // Deuxième partie = PDF binaire
            $binary = trim($partBody);
            if ($binary !== '') {
                return base64_encode($binary);
            }
        }

        return null;
    }
}
