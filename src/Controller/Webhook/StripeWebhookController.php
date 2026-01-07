<?php

namespace App\Controller\Webhook;

use App\Enum\OrderStatus;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Reçoit les webhooks Stripe (serveur-à-serveur), vérifie la signature,
 * puis met à jour la commande (Paid) de façon idempotente.
 *
 * Ne touche jamais à la session panier ici : ce n’est pas la session du client.
 */
final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly string $stripeWebhookSecret,
    ) {}

    #[Route('/webhooks/stripe', name: 'webhook_stripe', methods: ['POST'])]
    public function __invoke(
        Request $request,
        OrderRepository $orderRepository,
        EntityManagerInterface $em,
    ): Response {
        $payload = $request->getContent();
        $sigHeader = (string) $request->headers->get('Stripe-Signature', '');

        if ($sigHeader === '') {
            return new Response('Missing Stripe-Signature', 400, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        try {
            /** @var Event $event */
            $event = Webhook::constructEvent($payload, $sigHeader, $this->stripeWebhookSecret);
        } catch (SignatureVerificationException) {
            return new Response('Invalid signature', 400, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        } catch (\UnexpectedValueException) {
            return new Response('Invalid payload', 400, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        if ($event->type !== 'payment_intent.succeeded') {
            // On ignore le reste pour l’instant
            return new Response('ignored', 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        $paymentIntent = $event->data->object; // \Stripe\PaymentIntent
        $paymentIntentId = (string) ($paymentIntent->id ?? '');

        if ($paymentIntentId === '') {
            return new Response('Missing payment_intent id', 400, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        // 1) Recherche par paymentReference = pi_...
        $order = $orderRepository->findOneBy(['paymentReference' => $paymentIntentId]);

        // 2) Fallback robuste : metadata.order_id
        if ($order === null) {
            $orderId = (int) (($paymentIntent->metadata['order_id'] ?? 0));
            if ($orderId > 0) {
                $order = $orderRepository->find($orderId);
            }
        }

        if ($order === null) {
            // 200 pour éviter les retries infinis, mais ça doit être loggé si tu ajoutes un Logger
            return new Response('Order not found', 200, [
                'Content-Type' => 'text/plain; charset=utf-8',
            ]);
        }

        // Idempotence : si déjà payé, on ne refait pas
        if ($order->getStatus() !== OrderStatus::Paid) {
            $order->setStatus(OrderStatus::Paid);
            $order->setPaidAt(new \DateTimeImmutable());

            // Assure que paymentReference est bien le pi_...
            if (!$order->getPaymentReference()) {
                $order->setPaymentReference($paymentIntentId);
            }
        }

        // On marque que le panier doit être vidé côté session au prochain hit user
        if ($order->getCartClearedAt() === null) {
            $order->markCartCleared(); // met cartClearedAt = now
        }

        $em->flush();

        return new Response('ok', 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
