<?php

namespace App\Controller\Api;

use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ApiOrderController extends AbstractController
{
    #[Route('/api/order/{id<\d+>}', name: 'app_api_order_update', methods: ['PATCH'])]
    public function update(
        int $id,
        Request $request,
        OrderRepository $orderRepo,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        // Ownership: empêche de modifier la commande d’un autre user
        $order = $orderRepo->findOneBy(['id' => $id, 'user' => $user]);
        if (!$order) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        // Attendu: JSON { "shipping_address": "...", "billing_address": "..." }
        $payload = $request->toArray();

        $shipping = array_key_exists('shipping_address', $payload) ? $payload['shipping_address'] : null;
        $billing  = array_key_exists('billing_address', $payload) ? $payload['billing_address'] : null;

        if ($shipping !== null && !is_string($shipping)) {
            return $this->json(['error' => 'shipping_address must be a string'], 400);
        }
        if ($billing !== null && !is_string($billing)) {
            return $this->json(['error' => 'billing_address must be a string'], 400);
        }

        // Mise à jour partielle
        if ($shipping !== null) {
            $order->setShippingAddress($shipping);
        }
        if ($billing !== null) {
            $order->setBillingAddress($billing);
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
        ]);
    }
}
