<?php

namespace App\Controller\Api;

use App\Entity\User;
use App\Repository\AddressRepository;
use App\Repository\CarrierRepository;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ApiOrderController extends AbstractController
{
    #[Route('/api/order/{id<\d+>}', name: 'app_api_order_update', methods: ['PATCH'])]
    public function update(
        int $id,
        Request $request,
        OrderRepository $orderRepo,
        AddressRepository $addressRepo,
        CarrierRepository $carrierRepo,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $order = $orderRepo->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);

        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        $payload = $request->toArray();

        $shippingRaw = $payload['shipping_address'] ?? null;
        $billingRaw = $payload['billing_address'] ?? null;

        $shippingId = is_numeric($shippingRaw) ? (int) $shippingRaw : null;
        $billingId = is_numeric($billingRaw) ? (int) $billingRaw : null;

        if ($shippingId !== null) {
            $shippingAddress = $addressRepo->findOneBy([
                'id' => $shippingId,
                'user' => $user,
            ]);

            if (!$shippingAddress) {
                return $this->json(['error' => 'Invalid shipping address'], 403);
            }

            $order->setShippingAddress($shippingAddress->toMultilineSnapshot());

            if ($billingId === null && !$order->getBillingAddress()) {
                $order->setBillingAddress($shippingAddress->toMultilineSnapshot());
            }
        }

        if ($billingId !== null) {
            $billingAddress = $addressRepo->findOneBy([
                'id' => $billingId,
                'user' => $user,
            ]);

            if (!$billingAddress) {
                return $this->json(['error' => 'Invalid billing address'], 403);
            }

            $order->setBillingAddress($billingAddress->toMultilineSnapshot());
        }

        // Transporteur sélectionné — mise à jour du snapshot nom/prix + recalcul du total
        $carrierId = isset($payload['carrier_id']) && is_numeric($payload['carrier_id'])
            ? (int) $payload['carrier_id']
            : null;

        if ($carrierId !== null) {
            $carrier = $carrierRepo->find($carrierId);
            if ($carrier) {
                $oldCarrierPrice = $order->getCarrierPriceSnapshotCents();
                $newCarrierPrice = $carrier->getPrice();

                $order->setCarrierNameSnapshot($carrier->getName());
                $order->setCarrierPriceSnapshotCents($newCarrierPrice);

                // Recalculer le total TTC : soustraire l'ancien carrier, ajouter le nouveau
                $newTotal = $order->getOrderTotalTtcCents() - $oldCarrierPrice + $newCarrierPrice;
                $order->setOrderTotalTtcCents(max(0, $newTotal));
            }
        }

        // Point relais (Mondial Relay) — stocké en snapshot JSON sur la commande
        // L'adresse du point relais devient aussi l'adresse de livraison snapshot
        if (\array_key_exists('pickup_point', $payload)) {
            $pp = $payload['pickup_point'];
            if ($pp === null) {
                $order->setPickupPointSnapshot(null);
            } elseif (\is_array($pp) && !empty($pp['id'])) {
                $order->setPickupPointSnapshot(json_encode($pp, JSON_UNESCAPED_UNICODE));

                // Adresse de livraison = adresse du point relais (pour cohérence et paiement)
                $pickupShippingSnapshot = implode("\n", array_filter([
                    $pp['name'] ?? '',
                    $pp['address'] ?? '',
                    trim(($pp['postalCode'] ?? '') . ' ' . ($pp['city'] ?? '')),
                    'Point relais Mondial Relay',
                ]));
                $order->setShippingAddress($pickupShippingSnapshot);
            }
        }

        $violations = $validator->validate($order);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = ['field' => $violation->getPropertyPath(), 'message' => $violation->getMessage()];
            }
            return $this->json(['error' => 'Validation failed', 'violations' => $errors], 422);
        }

        $em->flush();

        return $this->json([
            'ok' => true,
            'orderId' => $order->getId(),
            'shipping_snapshot' => $order->getShippingAddress(),
            'billing_snapshot' => $order->getBillingAddress(),
        ]);
    }
}
