<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\FulfillmentStatus;
use App\Repository\OrderRepository;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ApiStripeController extends AbstractController
{
    #[Route('/api/stripe/payment-intent/{orderId<\d+>}', name: 'api_stripe_payment_intent_create', methods: ['POST'])]
    public function createPaymentIntent(
        int $orderId,
        OrderRepository $orderRepository,
        StripeService $stripeService,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $order = $orderRepository->find($orderId);
        if (!$order instanceof Order || $order->getUser()?->getId() !== $user->getId()) {
            return $this->json(['error' => 'Order not found'], 404);
        }

        if ($order->getFulfillmentStatus() !== FulfillmentStatus::Draft) {
            return $this->json(['error' => 'Order not payable'], 409);
        }

        $amount = (int) $order->getOrderTotalTtcCents();
        if ($amount <= 0) {
            return $this->json(['error' => 'Invalid amount'], 422);
        }

        Stripe::setApiKey($stripeService->getPrivateKey());

        try {
            // 1) Si un intent existe, on le récupère
            if ($order->getPaymentReference()) {
                $existing = PaymentIntent::retrieve($order->getPaymentReference());

                // ✅ Si déjà finalisé -> on ne le réutilise pas
                if (\in_array($existing->status, ['succeeded', 'canceled'], true)) {
                    $order->setPaymentReference(null);
                    $em->flush();
                } else {
                    // ✅ On n'update le montant que si le status le permet.
                    // Stripe permet update sur requires_payment_method / requires_confirmation / requires_action
                    if ((int) $existing->amount !== $amount) {
                        if (\in_array($existing->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
                            $existing = PaymentIntent::update($existing->id, ['amount' => $amount]);
                        } else {
                            // Statut non-updatable => on recrée
                            $order->setPaymentReference(null);
                            $em->flush();
                        }
                    }

                    // Si on a gardé l'existant
                    if ($order->getPaymentReference()) {
                        return $this->json([
                            'clientSecret' => $existing->client_secret,
                            'paymentIntentId' => $existing->id,
                            'status' => $existing->status,
                        ]);
                    }
                }
            }

            // 2) Créer un nouvel intent (idempotent par commande)
            $intent = PaymentIntent::create(
                [
                    'amount' => $amount,
                    'currency' => 'eur',
                    'automatic_payment_methods' => ['enabled' => true],
                    'metadata' => [
                        'order_id' => (string) $order->getId(),
                        'user_id' => (string) $user->getId(),
                    ],
                ],
                [
                    // ⚠️ Idempotency key: change-la si tu veux forcer un nouvel intent.
                    // Ici, comme on a purgé paymentReference si besoin, c’est OK.
                    'idempotency_key' => sprintf('pi_order_%d_amount_%d', $order->getId(), $amount),

                ]
            );

            $order->setPaymentReference($intent->id);
            $em->flush();

            return $this->json([
                'clientSecret' => $intent->client_secret,
                'paymentIntentId' => $intent->id,
                'status' => $intent->status,
            ]);
        } catch (ApiErrorException $e) {
            return $this->json([
                'error' => 'Stripe error',
                'message' => $e->getMessage(),
            ], 502);
        }
    }
}
