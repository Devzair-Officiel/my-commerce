<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\FulfillmentStatus;
use App\Repository\OrderRepository;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ApiStripeController
{
    public function __construct(
        private readonly OrderRepository        $orderRepository,
        private readonly StripeService          $stripeService,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface        $logger,
        private readonly TokenStorageInterface  $tokenStorage,
    ) {}

    #[Route('/api/stripe/payment-intent/{orderId<\d+>}', name: 'api_stripe_payment_intent_create', methods: ['POST'])]
    public function createPaymentIntent(int $orderId): JsonResponse
    {
        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], 401);
        }

        $order = $this->orderRepository->find($orderId);
        if (!$order instanceof Order || $order->getUser()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Order not found'], 404);
        }

        if ($order->getFulfillmentStatus() !== FulfillmentStatus::Draft) {
            return new JsonResponse(['error' => 'Order not payable'], 409);
        }

        $amount = (int) $order->getOrderTotalTtcCents();
        if ($amount <= 0) {
            return new JsonResponse(['error' => 'Invalid amount'], 422);
        }

        Stripe::setApiKey($this->stripeService->getPrivateKey());

        try {
            // 1) Si un intent existe déjà, on le récupère
            if ($order->getPaymentReference()) {
                $existing = PaymentIntent::retrieve($order->getPaymentReference());

                if (\in_array($existing->status, ['succeeded', 'canceled'], true)) {
                    // Intent finalisé → on repart de zéro
                    $order->setPaymentReference(null);
                    $this->em->flush();
                } else {
                    // Mettre à jour le montant si le statut le permet
                    if ((int) $existing->amount !== $amount) {
                        if (\in_array($existing->status, ['requires_payment_method', 'requires_confirmation', 'requires_action'], true)) {
                            $existing = PaymentIntent::update($existing->id, ['amount' => $amount]);
                        } else {
                            $order->setPaymentReference(null);
                            $this->em->flush();
                        }
                    }

                    if ($order->getPaymentReference()) {
                        return new JsonResponse([
                            'clientSecret'    => $existing->client_secret,
                            'paymentIntentId' => $existing->id,
                            'status'          => $existing->status,
                        ]);
                    }
                }
            }

            // 2) Créer un nouvel intent
            $intent = PaymentIntent::create(
                [
                    'amount'                    => $amount,
                    'currency'                  => 'eur',
                    'automatic_payment_methods' => ['enabled' => true],
                    'metadata'                  => [
                        'order_id' => (string) $order->getId(),
                        'user_id'  => (string) $user->getId(),
                    ],
                ],
                ['idempotency_key' => sprintf('pi_order_%d_amount_%d', $order->getId(), $amount)]
            );

            $order->setPaymentReference($intent->id);
            $this->em->flush();

            return new JsonResponse([
                'clientSecret'    => $intent->client_secret,
                'paymentIntentId' => $intent->id,
                'status'          => $intent->status,
            ]);
        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe API error on PaymentIntent creation', [
                'order_id'    => $orderId,
                'stripe_code' => $e->getStripeCode(),
                'message'     => $e->getMessage(),
            ]);

            return new JsonResponse(['error' => 'Le service de paiement est temporairement indisponible.'], 502);
        }
    }
}
