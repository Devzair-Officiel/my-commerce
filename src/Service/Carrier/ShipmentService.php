<?php

namespace App\Service\Carrier;

use App\Entity\Order;
use App\Entity\Shipment;
use App\Enum\CarrierType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestre la création d'étiquettes d'expédition via Colissimo (domicile ou point relais).
 *
 * Utilisation depuis l'admin :
 *   $shipment = $shipmentService->createLabel($order, weightGrams: 500);
 */
final class ShipmentService
{
    public function __construct(
        private readonly ColissimoClient        $colissimoClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
    ) {}

    /**
     * Crée une étiquette d'expédition pour la commande et persiste le Shipment.
     *
     * @throws \RuntimeException si le transporteur n'est pas configuré ou si l'API échoue
     */
    public function createLabel(Order $order, int $weightGrams): Shipment
    {
        $carrier = $order->getCarrier();
        if (!$carrier) {
            throw new \RuntimeException(\sprintf('La commande %s n\'a pas de transporteur assigné.', $order->getOrderReference() ?? $order->getId()));
        }

        $shipment = new Shipment();
        $shipment->setCustomerOrder($order);
        $shipment->setCarrier($carrier);
        $shipment->setWeightGrams($weightGrams);
        $shipment->setCreatedAt(new \DateTimeImmutable());

        try {
            match ($carrier->getType()) {
                CarrierType::Colissimo => $this->fillColissimo($shipment, $order, $weightGrams),
                CarrierType::Manual    => $this->fillManual($shipment),
            };
        } catch (\RuntimeException $e) {
            $shipment->setErrorMessage($e->getMessage());
            $this->logger->error('[ShipmentService] Échec création étiquette', [
                'order'     => $order->getId(),
                'carrier'   => $carrier->getName(),
                'exception' => $e->getMessage(),
            ]);
            $this->em->persist($shipment);
            $this->em->flush();
            throw $e;
        }

        $shipment->setShippedAt(new \DateTimeImmutable());
        $shipment->setSyncedAt(new \DateTimeImmutable());

        $this->em->persist($shipment);
        $this->em->flush();

        return $shipment;
    }

    private function fillColissimo(Shipment $shipment, Order $order, int $weightGrams): void
    {
        $pickupPoint = $order->getPickupPoint();

        if ($pickupPoint && !empty($pickupPoint['id'])) {
            // Livraison en point relais Colissimo
            $recipient = $this->extractRecipient($order);

            // Colissimo A2P exige le numéro de portable pour notifier le client par SMS
            $phone = $order->getUser()?->getPhone();
            if (!$phone || trim($phone) === '') {
                throw new \RuntimeException(
                    'Le numéro de téléphone portable du client est requis pour une livraison en point relais Colissimo. '
                    . 'Veuillez demander au client de renseigner son numéro dans son profil.'
                );
            }
            $recipient['mobileNumber'] = preg_replace('/\D/', '', $phone);

            $shipment->setPickupPointId($pickupPoint['id']);
            $shipment->setPickupPointName($pickupPoint['name'] ?? '');
            $shipment->setPickupPointAddress($pickupPoint['address'] ?? '');
            $shipment->setPickupPointCity($pickupPoint['city'] ?? '');

            $result = $this->colissimoClient->generateLabelPickupPoint([
                'weight'         => round($weightGrams / 1000, 3),
                'pickupPointId'  => $pickupPoint['id'],
                'recipient'      => $recipient,
                'orderReference' => $order->getOrderReference() ?? (string) $order->getId(),
            ]);
        } else {
            // Livraison à domicile
            $recipient = $this->extractRecipient($order);

            $result = $this->colissimoClient->generateLabel([
                'weight'         => round($weightGrams / 1000, 3),
                'recipient'      => $recipient,
                'orderReference' => $order->getOrderReference() ?? (string) $order->getId(),
            ]);
        }

        $trackingNumber = $result['trackingNumber'];
        $shipment->setLabelUrl('data:application/pdf;base64,' . $result['labelBase64']);
        $shipment->setTrackingNumber($trackingNumber);
        $shipment->setTrackingUrl($this->colissimoClient->getTrackingUrl($trackingNumber));
    }

    private function fillManual(Shipment $shipment): void
    {
        $shipment->setTrackingNumber('À renseigner');
        $shipment->setTrackingUrl('');
    }

    /**
     * Extrait les informations du destinataire depuis le snapshot texte multiligne de la commande.
     *
     * Format snapshot (toMultilineSnapshot) :
     *   Ligne 1 : client_name
     *   Ligne 2 : (nom alias)  — optionnel
     *   Ligne 3 : rue
     *   Ligne 4 : code_postal ville
     *   Ligne 5 : state/pays  — optionnel
     *
     * @return array{lastName: string, firstName: string, line2: string, address: string, city: string, postalCode: string, countryCode: string}
     */
    private function extractRecipient(Order $order): array
    {
        $raw = $order->getShippingAddress();
        if (!$raw || trim($raw) === '') {
            throw new \RuntimeException('Adresse de livraison manquante sur la commande.');
        }

        // Supprimer les lignes vides et renuméroter
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $raw)),
            static fn(string $l): bool => $l !== ''
        ));

        // Ligne "code_postal ville" : on cherche la première ligne qui commence par des chiffres
        $postalCode = '';
        $city       = '';
        $street     = '';

        foreach ($lines as $i => $line) {
            if (preg_match('/^(\d{4,6})\s+(.+)$/', $line, $m)) {
                $postalCode = $m[1];
                $city       = $m[2];
                // La ligne précédente est la rue
                $street = $lines[$i - 1] ?? '';
                break;
            }
        }

        // Nom complet : première ligne (client_name)
        $fullName  = $lines[0] ?? '';
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName  = $nameParts[1] ?? $order->getUser()?->getLastname() ?? '';

        // Fallback si le parsing n'a rien trouvé
        if ($street === '' && isset($lines[1])) {
            $street = $lines[1];
        }

        return [
            'lastName'    => $lastName  !== '' ? $lastName  : ($order->getUser()?->getLastname()  ?? ''),
            'firstName'   => $firstName !== '' ? $firstName : ($order->getUser()?->getFirstname() ?? ''),
            'line2'       => $street,
            'address'     => $street,
            'city'        => $city,
            'zipCode'     => $postalCode,  // generateLabel
            'postalCode'  => $postalCode,  // generateLabelPickupPoint
            'countryCode' => 'FR',
        ];
    }
}
