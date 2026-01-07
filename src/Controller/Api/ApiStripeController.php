<?php

namespace App\Controller\Api;

use App\Entity\Order;
use App\Entity\User;
use App\Enum\FulfillmentStatus;
use App\Repository\OrderRepository;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
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
        EntityManagerInterface $em
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

        // On évite de créer un intent si la commande n’est pas au bon état
        if ($order->getFulfillmentStatus() !== FulfillmentStatus::Draft) {
            return $this->json(['error' => 'Order not payable'], 409);
        }

        $amount = (int) $order->getOrderTotalTtcCents();
        if ($amount <= 0) {
            return $this->json(['error' => 'Invalid amount'], 422);
        }

        Stripe::setApiKey($stripeService->getPrivateKey());

        $intent = PaymentIntent::create([
            'amount' => $amount,
            'currency' => 'eur',
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'order_id' => (string) $order->getId(),
                'user_id' => (string) $user->getId(),
            ],
        ]);

        // Recommandation: stocker l’ID du PaymentIntent (pas le client_secret)
        $order->setPaymentReference($intent->id);
        $em->flush();

        return $this->json([
            'clientSecret' => $intent->client_secret,
        ]);
    }
}
