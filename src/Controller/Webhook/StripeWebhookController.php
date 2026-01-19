<?php

namespace App\Controller\Webhook;

use Stripe\Event;
use Stripe\Webhook;
use App\Enum\PaymentStatus;
use Doctrine\DBAL\LockMode;
use Psr\Log\LoggerInterface;
use App\Enum\FulfillmentStatus;
use App\Service\StockAllocator;
use App\Entity\StripeWebhookEvent;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly string $stripeWebhookSecret,
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orderRepository,
        private readonly StockAllocator $stockAllocator,
        #[Autowire(service: 'limiter.stripe_webhook')]
        private readonly RateLimiterFactory $stripeWebhookLimiter,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/webhooks/stripe', name: 'webhook_stripe', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $sigHeader = (string) $request->headers->get('Stripe-Signature', '');
        if ($sigHeader === '') {
            return new Response('Missing Stripe-Signature', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $limit = $this->stripeWebhookLimiter->create('stripe_webhook')->consume(1);
        if (!$limit->isAccepted()) {
            return new Response('Too Many Requests', 429, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $payload = $request->getContent();

        try {
            /** @var Event $event */
            $event = Webhook::constructEvent($payload, $sigHeader, $this->stripeWebhookSecret);
        } catch (SignatureVerificationException) {
            return new Response('Invalid signature', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        } catch (\UnexpectedValueException) {
            return new Response('Invalid payload', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $handled = [
            'payment_intent.succeeded',
            'payment_intent.payment_failed',
            'payment_intent.canceled',
        ];

        if (!\in_array($event->type, $handled, true)) {
            return new Response('ignored', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $pi = $event->data->object;
        $piId = (string) ($pi->id ?? '');
        if ($piId === '') {
            return new Response('Missing payment_intent id', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        try {
            $this->em->wrapInTransaction(function () use ($event, $pi, $piId): void {
                // Idempotence : si l'event est déjà en base, UniqueConstraintViolationException
                $this->em->persist(new StripeWebhookEvent((string) $event->id, (string) $event->type));
                $this->em->flush();

                // Find order
                $order = $this->orderRepository->findOneBy(['paymentReference' => $piId]);

                if ($order === null) {
                    $orderId = (int) (($pi->metadata->order_id ?? 0));
                    if ($orderId > 0) {
                        $order = $this->orderRepository->find($orderId);
                    }
                }

                if ($order === null) {
                    throw new \RuntimeException('Order not found for payment_intent.');
                }

                $this->em->lock($order, LockMode::PESSIMISTIC_WRITE);

                // Ne jamais rétrograder une commande payée
                if ($order->getPaymentStatus() === PaymentStatus::Paid) {
                    return;
                }

                if (!$order->getPaymentReference()) {
                    $order->setPaymentReference($piId);
                }

                if ($event->type === 'payment_intent.succeeded') {
                    $amountReceived = isset($pi->amount_received) ? (int) $pi->amount_received : null;
                    if ($amountReceived !== null && $amountReceived !== (int) $order->getOrderTotalTtcCents()) {
                        throw new \RuntimeException('Amount mismatch.');
                    }

                    $this->stockAllocator->decrementStockForPaidOrder($order);

                    $order->setPaymentStatus(PaymentStatus::Paid);
                    $order->setFulfillmentStatus(FulfillmentStatus::Preparing); // ✅ SORT DU DRAFT
                    $order->setPaidAt(new \DateTimeImmutable());
                    $order->setPaymentFailureReason(null);
                }

                if ($event->type === 'payment_intent.payment_failed') {
                    $order->setPaymentStatus(PaymentStatus::Failed);

                    $reason = null;
                    if (isset($pi->last_payment_error) && isset($pi->last_payment_error->message)) {
                        $reason = (string) $pi->last_payment_error->message;
                    }
                    $order->setPaymentFailureReason($reason);
                }

                if ($event->type === 'payment_intent.canceled') {
                    $order->setPaymentStatus(PaymentStatus::Failed);

                    // Stripe peut fournir cancellation_reason sur l'intent
                    $reason = null;
                    if (isset($pi->cancellation_reason) && $pi->cancellation_reason !== null) {
                        $reason = 'Canceled: ' . (string) $pi->cancellation_reason;
                    } else {
                        $reason = 'Canceled';
                    }
                    $order->setPaymentFailureReason($reason);
                }

                $this->em->flush();
            });
        } catch (UniqueConstraintViolationException) {
            // Event déjà traité
            return new Response('ok', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        } catch (\Throwable $e) {
            $this->logger->error('Stripe webhook error', [
                'exception' => $e,
                'event_id' => (string) ($event->id ?? ''),
                'type' => (string) ($event->type ?? ''),
                'payment_intent' => $piId,
            ]);

            // 500 => Stripe retente (utile si bug temporaire)
            return new Response('error', 500, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return new Response('ok', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
