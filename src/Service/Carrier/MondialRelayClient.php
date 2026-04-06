<?php

namespace App\Service\Carrier;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client pour l'API Mondial Relay (SOAP via HTTP GET).
 *
 * Authentification : Enseigne (code client) + PrivateKey (clé secrète).
 * L'API utilise une signature MD5 calculée sur les paramètres + PrivateKey.
 *
 * URL API : https://api.mondialrelay.com/WebService.asmx
 * Credentials test : Enseigne=TTMRSDBX (voir .env MONDIAL_RELAY_ENSEIGNE/PRIVATE_KEY)
 *
 * Documentation : https://www.mondialrelay.fr/media/108937/wsdl_mws_v5-1-0.pdf
 */
final class MondialRelayClient
{
    private const BASE_URL = 'https://api.mondialrelay.com/WebService.asmx';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $enseigne,
        private readonly string $privateKey,
    ) {}

    /**
     * Recherche les points relais proches d'un code postal.
     *
     * @return array<int, array{
     *   id: string,
     *   name: string,
     *   address: string,
     *   city: string,
     *   postalCode: string,
     *   latitude: string,
     *   longitude: string,
     *   openingHours: string,
     * }>
     */
    public function findPickupPoints(string $postalCode, string $countryCode = 'FR', int $maxResults = 7): array
    {
        // WSI4 signature field order (must match exactly)
        $enseigne       = $this->enseigne;
        $pays           = $countryCode;
        $numPointRelais = '';
        $ville          = '';
        $cp             = $postalCode;
        $latitude       = '';
        $longitude      = '';
        $taille         = '';
        $poids          = '';
        $action         = 'REL';
        $delaiEnvoi     = '0';
        $rayonRecherche = '20';
        $typeActivite   = '';
        $nace           = '';
        $nombreResultats = (string) $maxResults;

        $chain    = $enseigne . $pays . $numPointRelais . $ville . $cp
                  . $latitude . $longitude . $taille . $poids . $action
                  . $delaiEnvoi . $rayonRecherche . $typeActivite . $nace
                  . $nombreResultats . $this->privateKey;
        $security = strtoupper(md5($chain));

        $soapBody = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
               xmlns:xsd="http://www.w3.org/2001/XMLSchema"
               xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
  <soap:Body>
    <WSI4_PointRelais_Recherche xmlns="http://www.mondialrelay.fr/webservice/">
      <Enseigne>{$enseigne}</Enseigne>
      <Pays>{$pays}</Pays>
      <NumPointRelais>{$numPointRelais}</NumPointRelais>
      <Ville>{$ville}</Ville>
      <CP>{$cp}</CP>
      <Latitude>{$latitude}</Latitude>
      <Longitude>{$longitude}</Longitude>
      <Taille>{$taille}</Taille>
      <Poids>{$poids}</Poids>
      <Action>{$action}</Action>
      <DelaiEnvoi>{$delaiEnvoi}</DelaiEnvoi>
      <RayonRecherche>{$rayonRecherche}</RayonRecherche>
      <TypeActivite>{$typeActivite}</TypeActivite>
      <NACE>{$nace}</NACE>
      <NombreResultats>{$nombreResultats}</NombreResultats>
      <Security>{$security}</Security>
    </WSI4_PointRelais_Recherche>
  </soap:Body>
</soap:Envelope>
XML;

        try {
            $response = $this->httpClient->request('POST', self::BASE_URL, [
                'headers' => [
                    'Content-Type' => 'text/xml; charset=utf-8',
                    'SOAPAction'   => '"http://www.mondialrelay.fr/webservice/WSI4_PointRelais_Recherche"',
                ],
                'body' => $soapBody,
            ]);
            $raw = $response->getContent();
        } catch (\Throwable $e) {
            $this->logger->error('[MondialRelay] Erreur HTTP findPickupPoints', ['exception' => $e->getMessage()]);
            throw new \RuntimeException('Mondial Relay API unreachable: ' . $e->getMessage(), 0, $e);
        }

        try {
            $xml = new \SimpleXMLElement($raw);
        } catch (\Throwable $e) {
            $this->logger->error('[MondialRelay] XML invalide', ['body' => substr($raw, 0, 500)]);
            throw new \RuntimeException('Mondial Relay: réponse XML invalide');
        }

        // Unwrap SOAP envelope
        $xml->registerXPathNamespace('soap', 'http://schemas.xmlsoap.org/soap/envelope/');
        $xml->registerXPathNamespace('mr',   'http://www.mondialrelay.fr/webservice/');

        $results = $xml->xpath('//mr:WSI4_PointRelais_RechercheResult');
        $result  = $results[0] ?? null;

        if ($result === null) {
            $this->logger->error('[MondialRelay] Nœud résultat introuvable', ['body' => substr($raw, 0, 1000)]);
            throw new \RuntimeException('Mondial Relay: structure de réponse inattendue');
        }

        $stat = (string) ($result->STAT ?? '');
        if ($stat !== '0') {
            $this->logger->error('[MondialRelay] STAT non-zéro', ['stat' => $stat, 'cp' => $postalCode]);
            throw new \RuntimeException(\sprintf('Mondial Relay error stat=%s pour CP=%s', $stat, $postalCode));
        }

        $points = [];
        foreach ($result->PointsRelais->PointRelais_Details ?? [] as $pr) {
            $lat = str_replace(',', '.', (string) $pr->Latitude);
            $lng = str_replace(',', '.', (string) $pr->Longitude);

            $points[] = [
                'id'           => (string) $pr->Num,
                'name'         => (string) $pr->LgAdr1,
                'address'      => trim((string) $pr->LgAdr3 . ' ' . (string) $pr->LgAdr4),
                'city'         => (string) $pr->Ville,
                'postalCode'   => (string) $pr->CP,
                'latitude'     => $lat,
                'longitude'    => $lng,
                'openingHours' => $this->formatOpeningHours($pr),
            ];
        }

        return $points;
    }

    /**
     * Crée une étiquette Mondial Relay pour un point relais.
     *
     * @param array{
     *   weight: int,                Poids en grammes
     *   pickupPointId: string,      Numéro du point relais
     *   recipient: array{
     *     lastName: string,
     *     firstName: string,
     *     address: string,
     *     city: string,
     *     postalCode: string,
     *     countryCode: string,
     *     phone: string,
     *     email: string,
     *   },
     *   orderReference: string,
     * } $params
     *
     * @return array{trackingNumber: string, labelUrl: string}
     */
    public function createParcel(array $params): array
    {
        $p = [
            'Enseigne'         => $this->enseigne,
            'ModeCol'          => 'REL',
            'ModeLiv'          => '24R',  // Livraison point relais 24h
            'NDossier'         => $params['orderReference'],
            'NClient'          => '',
            'Expe_Langage'     => 'FR',
            'Expe_Ad1'         => 'Nidemiel',
            'Expe_Ad2'         => '',
            'Expe_Ad3'         => '',
            'Expe_Ad4'         => '',
            'Expe_Ville'       => '',
            'Expe_CP'          => '',
            'Expe_Pays'        => 'FR',
            'Expe_Tel1'        => '',
            'Expe_Mail'        => '',
            'Dest_Langage'     => 'FR',
            'Dest_Ad1'         => $params['recipient']['lastName'] . ' ' . $params['recipient']['firstName'],
            'Dest_Ad2'         => '',
            'Dest_Ad3'         => $params['recipient']['address'],
            'Dest_Ad4'         => '',
            'Dest_Ville'       => $params['recipient']['city'],
            'Dest_CP'          => $params['recipient']['postalCode'],
            'Dest_Pays'        => $params['recipient']['countryCode'] ?? 'FR',
            'Dest_Tel1'        => $params['recipient']['phone'] ?? '',
            'Dest_Mail'        => $params['recipient']['email'] ?? '',
            'Poids'            => (string) $params['weight'],
            'NbColis'          => '1',
            'CRT_Valeur'       => '0',
            'CRT_Devise'       => 'EUR',
            'Exp_Valeur'       => '0',
            'Exp_Devise'       => 'EUR',
            'COL_Rel_Pays'     => 'FR',
            'COL_Rel'          => '004100',  // Point de collecte (dépôt chez MR)
            'LIV_Rel_Pays'     => $params['recipient']['countryCode'] ?? 'FR',
            'LIV_Rel'          => $params['pickupPointId'],
            'TAvisage'         => '',
            'TReprise'         => '',
            'Montage'          => '',
            'TRDV'             => '',
            'Assurance'        => '',
            'Instructions'     => '',
            'Texte'            => '',
        ];

        $signatureFields = array_keys($p);
        $p['Security'] = $this->computeSignature($p, $signatureFields);

        try {
            $response = $this->httpClient->request('GET', 'https://api.mondialrelay.com/WebService.asmx/WSI2_CreationEtiquette', [
                'query' => $p,
            ]);
            $xml = new \SimpleXMLElement($response->getContent());
        } catch (\Throwable $e) {
            $this->logger->error('[MondialRelay] Erreur HTTP createParcel', ['exception' => $e->getMessage()]);
            throw new \RuntimeException('Mondial Relay API unreachable: ' . $e->getMessage(), 0, $e);
        }

        $stat = (string) ($xml->STAT ?? '');
        if ($stat !== '0') {
            throw new \RuntimeException(\sprintf('Mondial Relay error stat=%s pour commande=%s', $stat, $params['orderReference']));
        }

        $trackingNumber = (string) ($xml->ExpeditionNum ?? '');
        $labelUrl       = (string) ($xml->URL_Etiquette ?? '');

        if (!$trackingNumber) {
            throw new \RuntimeException('Mondial Relay: numéro de suivi manquant dans la réponse');
        }

        $this->logger->info('[MondialRelay] Colis créé', ['tracking' => $trackingNumber]);

        return [
            'trackingNumber' => $trackingNumber,
            'labelUrl'       => $labelUrl,
        ];
    }

    /**
     * Retourne l'URL de suivi publique Mondial Relay.
     */
    public function getTrackingUrl(string $trackingNumber): string
    {
        return \sprintf(
            'https://www.mondialrelay.fr/suivi-de-colis/?numColis=%s&codePostal=',
            urlencode($trackingNumber)
        );
    }

    /**
     * Calcule la signature MD5 Mondial Relay.
     * Concatène les valeurs des champs dans l'ordre donné + PrivateKey, puis MD5 en majuscules.
     *
     * @param array<string, string> $params
     * @param array<string>         $fields
     */
    private function computeSignature(array $params, array $fields): string
    {
        $chain = implode('', array_map(fn(string $f) => $params[$f] ?? '', $fields));
        $chain .= $this->privateKey;

        return strtoupper(md5($chain));
    }

    private function formatOpeningHours(\SimpleXMLElement $pr): string
    {
        $days = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $fields = ['Horaires_Lundi', 'Horaires_Mardi', 'Horaires_Mercredi', 'Horaires_Jeudi', 'Horaires_Vendredi', 'Horaires_Samedi', 'Horaires_Dimanche'];
        $lines = [];
        foreach ($fields as $i => $field) {
            $val = (string) ($pr->$field ?? '');
            if ($val) {
                $lines[] = $days[$i] . ': ' . $val;
            }
        }
        return implode(' | ', $lines);
    }
}
