<?php

namespace App\Service\Carrier;

use App\Entity\Order;
use App\Entity\Shipment;
use App\Enum\CarrierType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Orchestre la création d'étiquettes d'expédition via Colissimo ou Mondial Relay.
 *
 * Utilisation depuis l'admin :
 *   $shipment = $shipmentService->createLabel($order, weightGrams: 500);
 */
final class ShipmentService
{
    public function __construct(
        private readonly ColissimoClient     $colissimoClient,
        private readonly MondialRelayClient  $mondialRelayClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface     $logger,
    ) {}

    /**
     * Crée une étiquette d'expédition pour la commande et persiste le Shipment.
     *
     * @param int $weightGrams Poids du colis en grammes (saisi par l'admin)
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
                CarrierType::Colissimo    => $this->fillColissimo($shipment, $order, $weightGrams),
                CarrierType::MondialRelay => $this->fillMondialRelay($shipment, $order, $weightGrams),
                CarrierType::Manual       => $this->fillManual($shipment),
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
        $recipient = $this->extractRecipient($order);

        $result = $this->colissimoClient->generateLabel([
            'weight'         => round($weightGrams / 1000, 3),
            'recipient'      => $recipient,
            'orderReference' => $order->getOrderReference() ?? (string) $order->getId(),
        ]);

        $trackingNumber = $result['trackingNumber'];

        // Stocker le PDF base64 comme data-URL pour accès depuis l'admin
        $shipment->setLabelUrl('data:application/pdf;base64,' . $result['labelBase64']);
        $shipment->setTrackingNumber($trackingNumber);
        $shipment->setTrackingUrl($this->colissimoClient->getTrackingUrl($trackingNumber));
    }

    private function fillMondialRelay(Shipment $shipment, Order $order, int $weightGrams): void
    {
        $recipient = $this->extractRecipient($order);
        $recipient['phone'] = $order->getUser()?->getPhone() ?? '';
        $recipient['email'] = $order->getUser()?->getEmail() ?? '';

        // Le point relais est snapshot sur la commande au moment du checkout
        $pickupPoint = $order->getPickupPoint();
        if (!$pickupPoint || empty($pickupPoint['id'])) {
            throw new \RuntimeException('Mondial Relay : aucun point relais sélectionné pour cette commande.');
        }
        $pickupPointId = $pickupPoint['id'];

        // Copier les infos du point relais sur le Shipment pour historisation
        $shipment->setPickupPointId($pickupPointId);
        $shipment->setPickupPointName($pickupPoint['name'] ?? '');
        $shipment->setPickupPointAddress($pickupPoint['address'] ?? '');
        $shipment->setPickupPointCity($pickupPoint['city'] ?? '');

        $result = $this->mondialRelayClient->createParcel([
            'weight'         => $weightGrams,
            'pickupPointId'  => $pickupPointId,
            'recipient'      => $recipient,
            'orderReference' => $order->getOrderReference() ?? (string) $order->getId(),
        ]);

        $trackingNumber = $result['trackingNumber'];

        $shipment->setLabelUrl($result['labelUrl']);
        $shipment->setTrackingNumber($trackingNumber);
        $shipment->setTrackingUrl($this->mondialRelayClient->getTrackingUrl($trackingNumber));
    }

    private function fillManual(Shipment $shipment): void
    {
        // Pour un transporteur manuel, l'admin saisit le numéro de suivi directement
        // On ne fait rien ici — les champs seront remplis via EasyAdmin
        $shipment->setTrackingNumber('À renseigner');
        $shipment->setTrackingUrl('');
    }

    /**
     * Extrait les informations destinataire depuis l'adresse de livraison JSON de la commande.
     *
     * @return array{lastName: string, firstName: string, line2: string, city: string, postalCode: string, countryCode: string}
     */
    private function extractRecipient(Order $order): array
    {
        $raw = $order->getShippingAddress();
        if (!$raw) {
            throw new \RuntimeException('Adresse de livraison manquante sur la commande.');
        }

        $addr = json_decode($raw, true);
        if (!is_array($addr)) {
            throw new \RuntimeException('Adresse de livraison invalide (JSON malformé).');
        }

        return [
            'lastName'    => $addr['last_name']    ?? ($order->getUser()?->getLastname()  ?? ''),
            'firstName'   => $addr['first_name']   ?? ($order->getUser()?->getFirstname() ?? ''),
            'line2'       => trim(($addr['address_line1'] ?? '') . ' ' . ($addr['address_line2'] ?? '')),
            'address'     => trim(($addr['address_line1'] ?? '') . ' ' . ($addr['address_line2'] ?? '')),
            'city'        => $addr['city']          ?? '',
            'postalCode'  => $addr['postal_code']   ?? $addr['zip_code'] ?? '',
            'countryCode' => $addr['country_code']  ?? 'FR',
        ];
    }
}
