<?php

namespace App\Service\Carrier;

use App\Entity\Shipment;
use App\Entity\ShipmentStatus;
use App\Enum\FulfillmentStatus;
use App\Message\SendDeliveredEmailMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Synchronise les statuts de suivi Colissimo depuis l'API vers la base de données.
 */
final class ColissimoTrackingService
{
    // Codes Colissimo qui indiquent une livraison finale
    private const DELIVERED_CODES = ['LIVCFM', 'LIVGAR', 'LIVDOM'];

    public function __construct(
        private readonly ColissimoClient        $colissimoClient,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
        private readonly MessageBusInterface    $bus,
    ) {}

    /**
     * Synchronise le suivi d'un seul colis.
     *
     * @return int Nombre de nouveaux événements insérés
     */
    public function syncShipment(Shipment $shipment): int
    {
        $trackingNumber = $shipment->getTrackingNumber();

        if (!$trackingNumber) {
            return 0;
        }

        try {
            $tracking = $this->colissimoClient->getTracking($trackingNumber);
        } catch (\RuntimeException $e) {
            $this->logger->warning('[ColissimoTracking] Impossible de récupérer le suivi', [
                'tracking'  => $trackingNumber,
                'exception' => $e->getMessage(),
            ]);
            $shipment->setErrorMessage($e->getMessage());
            $shipment->setSyncedAt(new \DateTimeImmutable());
            $this->em->flush();

            return 0;
        }

        // Codes déjà enregistrés pour ce colis (pour éviter les doublons)
        $existingKeys = $this->buildExistingKeys($shipment);

        $inserted = 0;

        foreach ($tracking['events'] as $event) {
            $occuredAt = $this->parseDate($event['date'] ?? null);
            $key       = $event['code'] . '|' . ($occuredAt?->format('Y-m-d H:i') ?? '');

            if (isset($existingKeys[$key])) {
                continue;
            }

            $status = new ShipmentStatus();
            $status->setShipment($shipment);
            $status->setStatusCode($event['code']);
            $status->setLabel($event['label'] ?? $event['code']);
            $status->setOccuredAt($occuredAt);
            $status->setRawData($event['rawData']);

            $this->em->persist($status);
            $existingKeys[$key] = true;
            $inserted++;
        }

        // Mettre à jour le code statut courant
        $shipment->setStatusCode($tracking['statusCode']);
        $shipment->setSyncedAt(new \DateTimeImmutable());
        $shipment->setErrorMessage(null);

        // Marquer livré si nécessaire
        if (\in_array($tracking['statusCode'], self::DELIVERED_CODES, true) && $shipment->getDeliveredAt() === null) {
            $deliveryDate = $this->findDeliveryDate($tracking['events']) ?? new \DateTimeImmutable();
            $shipment->setDeliveredAt($deliveryDate);

            // Mettre à jour le FulfillmentStatus de la commande
            $order = $shipment->getCustomerOrder();
            if ($order !== null && $order->getFulfillmentStatus() !== FulfillmentStatus::Delivered) {
                $order->setFulfillmentStatus(FulfillmentStatus::Delivered);
            }

            $this->logger->info('[ColissimoTracking] Colis livré', [
                'tracking'    => $trackingNumber,
                'deliveredAt' => $deliveryDate->format('Y-m-d H:i'),
            ]);

            // Envoyer l'email de livraison via Messenger (async)
            $this->bus->dispatch(new SendDeliveredEmailMessage($shipment->getId()));
        }

        $this->em->flush();

        $this->logger->info('[ColissimoTracking] Sync terminée', [
            'tracking' => $trackingNumber,
            'inserted' => $inserted,
            'status'   => $tracking['statusCode'],
        ]);

        return $inserted;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function buildExistingKeys(Shipment $shipment): array
    {
        $keys = [];
        foreach ($shipment->getShipmentStatuses() as $status) {
            $key = $status->getStatusCode() . '|' . ($status->getOccuredAt()?->format('Y-m-d H:i') ?? '');
            $keys[$key] = true;
        }
        return $keys;
    }

    private function parseDate(?string $date): ?\DateTimeImmutable
    {
        if (!$date) {
            return null;
        }

        // L'API Colissimo retourne des dates au format "dd/MM/yyyy HH:mm:ss" ou ISO
        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', \DateTimeInterface::ATOM, \DateTimeInterface::ISO8601_EXPANDED] as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $date);
            if ($dt !== false) {
                return $dt;
            }
        }

        try {
            return new \DateTimeImmutable($date);
        } catch (\Throwable) {
            return null;
        }
    }

    private function findDeliveryDate(array $events): ?\DateTimeImmutable
    {
        foreach (array_reverse($events) as $event) {
            if (\in_array($event['code'], self::DELIVERED_CODES, true)) {
                return $this->parseDate($event['date'] ?? null);
            }
        }
        return null;
    }
}
