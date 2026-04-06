<?php

namespace App\Service\Carrier;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client pour l'API Colissimo (La Poste).
 *
 * Authentification : login + password du compte Colissimo Pro.
 * Environnement bac à sable : https://ws.colissimo.fr/api-lettre/rest/generateLabel (même URL, credentials de test).
 *
 * Documentation : https://www.colissimo.entreprise.laposte.fr/fr/developpeurs
 */
final class ColissimoClient
{
    private const BASE_URL = 'https://ws.colissimo.fr/api-lettre/rest';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $login,
        private readonly string $password,
    ) {}

    /**
     * Génère une étiquette Colissimo pour une livraison à domicile.
     *
     * @param array{
     *   weight: float,          Poids en kg (ex: 0.5)
     *   recipient: array{
     *     lastName: string,
     *     firstName: string,
     *     line2: string,        Adresse ligne 2 (numéro + voie)
     *     countryCode: string,  'FR'
     *     city: string,
     *     zipCode: string,
     *   },
     *   orderReference: string, Référence commande (ex: CMD-2026-0001)
     * } $params
     *
     * @return array{trackingNumber: string, labelBase64: string}
     *
     * @throws \RuntimeException si l'API retourne une erreur
     */
    public function generateLabel(array $params): array
    {
        $payload = [
            'contractNumber' => $this->login,
            'password'       => $this->password,
            'outputFormat'   => [
                'x'              => 0,
                'y'              => 0,
                'outputPrintingType' => 'PDF_10x15_300dpi',
            ],
            'letter' => [
                'service' => [
                    'productCode'      => 'DOM',  // Livraison domicile standard
                    'depositDate'      => (new \DateTimeImmutable())->format('d/m/Y'),
                    'orderNumber'      => $params['orderReference'],
                ],
                'parcel' => [
                    'weight' => $params['weight'],
                ],
                'sender' => [
                    'address' => [
                        'companyName' => 'Nidemiel',
                        'countryCode' => 'FR',
                    ],
                ],
                'addressee' => [
                    'address' => [
                        'lastName'    => $params['recipient']['lastName'],
                        'firstName'   => $params['recipient']['firstName'],
                        'line2'       => $params['recipient']['line2'],
                        'countryCode' => $params['recipient']['countryCode'] ?? 'FR',
                        'city'        => $params['recipient']['city'],
                        'zipCode'     => $params['recipient']['zipCode'],
                    ],
                ],
            ],
        ];

        try {
            $response = $this->httpClient->request('POST', self::BASE_URL . '/generateLabel', [
                'json' => $payload,
            ]);

            $data = $response->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('[Colissimo] Erreur HTTP generateLabel', ['exception' => $e->getMessage()]);
            throw new \RuntimeException('Colissimo API unreachable: ' . $e->getMessage(), 0, $e);
        }

        if (!empty($data['messages'])) {
            foreach ($data['messages'] as $msg) {
                if (($msg['type'] ?? '') === 'ERROR') {
                    $this->logger->error('[Colissimo] Erreur API', ['message' => $msg]);
                    throw new \RuntimeException(\sprintf('Colissimo error %s: %s', $msg['id'] ?? '?', $msg['messageContent'] ?? '?'));
                }
            }
        }

        $trackingNumber = $data['labelResponse']['parcelNumber'] ?? null;
        $labelBase64    = $data['labelResponse']['label']        ?? null;

        if (!$trackingNumber || !$labelBase64) {
            throw new \RuntimeException('Colissimo: réponse inattendue (parcelNumber ou label manquant)');
        }

        $this->logger->info('[Colissimo] Étiquette générée', ['tracking' => $trackingNumber]);

        return [
            'trackingNumber' => $trackingNumber,
            'labelBase64'    => $labelBase64,
        ];
    }

    /**
     * Retourne l'URL de suivi publique Colissimo pour un numéro de colis.
     */
    public function getTrackingUrl(string $trackingNumber): string
    {
        return \sprintf(
            'https://www.laposte.fr/outils/suivre-vos-envois?code=%s',
            urlencode($trackingNumber)
        );
    }
}
