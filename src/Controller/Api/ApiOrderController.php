<?php

namespace App\Controller\Api;

use App\Entity\Address;
use App\Entity\User;
use App\Repository\AddressRepository;
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
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Ownership: empêche de modifier la commande d’un autre user
        $order = $orderRepo->findOneBy(['id' => $id, 'user' => $user]);
        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        $payload = $request->toArray();

        // ✅ On attend des IDs, pas des strings
        $shippingRaw = $payload['shipping_address'] ?? null;
        $billingRaw  = $payload['billing_address'] ?? null;

        $shippingId = is_numeric($shippingRaw) ? (int) $shippingRaw : null;
        $billingId  = is_numeric($billingRaw) ? (int) $billingRaw : null;

        if ($shippingId !== null) {
            $shippingAddress = $addressRepo->find($shippingId);

            if (!$shippingAddress instanceof Address || $shippingAddress->getUser() !== $user) {
                return $this->json(['error' => 'Invalid shipping address'], 403);
            }

            // ✅ SNAPSHOT TEXTE
            $order->setShippingAddress($shippingAddress->toSnapshotString());
        }

        if ($billingId !== null) {
            $billingAddress = $addressRepo->find($billingId);

            if (!$billingAddress instanceof Address || $billingAddress->getUser() !== $user) {
                return $this->json(['error' => 'Invalid billing address'], 403);
            }

            $order->setBillingAddress($billingAddress->toSnapshotString());
        }

        $violations = $validator->validate($order);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = [
                    'field' => $violation->getPropertyPath(),
                    'message' => $violation->getMessage(),
                ];
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
