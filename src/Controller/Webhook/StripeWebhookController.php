<?php

namespace App\Controller\Webhook;

use Stripe\Event;
use Stripe\Refund;
use Stripe\Webhook;
use App\Enum\PaymentStatus;
use Doctrine\DBAL\LockMode;
use Psr\Log\LoggerInterface;
use App\Enum\FulfillmentStatus;
use App\Message\SendOrderConfirmationEmailMessage;
use App\Repository\PaymentMethodRepository;
use App\Service\StockAllocator;
use App\Entity\StripeWebhookEvent;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Stripe\Exception\SignatureVerificationException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Contrôleur de webhook Stripe : réceptionne les événements de paiement,
 * met à jour les statuts de commande et déclenche les actions post-paiement (stock, facture, e-mails).
 */
final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly string $stripeWebhookSecret,
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orderRepository,
        private readonly PaymentMethodRepository $paymentMethodRepository,
        private readonly StockAllocator $stockAllocator,
        private readonly MessageBusInterface $messageBus,
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
            'charge.refunded',
        ];

        if (!\in_array($event->type, $handled, true)) {
            return new Response('ignored', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $obj = $event->data->object;

        // charge.refunded → l'objet est un Charge, le payment_intent est dans ->payment_intent
        if ($event->type === 'charge.refunded') {
            $piId = (string) ($obj->payment_intent ?? '');
        } else {
            $piId = (string) ($obj->id ?? '');
        }

        $pi = $obj; // alias pour compatibilité avec le code existant

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
                        $this->logger->error('Stripe amount mismatch', [
                            'order_id'        => $order->getId(),
                            'expected_cents'  => $order->getOrderTotalTtcCents(),
                            'received_cents'  => $amountReceived,
                            'payment_intent'  => $piId,
                        ]);
                        throw new \RuntimeException('Amount mismatch.');
                    }

                    // Guard: décrémenter le stock une seule fois même si le webhook est rejoué
                    if (!$order->isStockDecremented()) {
                        try {
                            $this->stockAllocator->decrementStockForPaidOrder($order);
                            $order->markStockDecremented();
                        } catch (\RuntimeException $e) {
                            // Stock insuffisant après paiement → remboursement automatique
                            $this->logger->critical('Stock insuffisant après paiement, remboursement déclenché', [
                                'order_id'       => $order->getId(),
                                'payment_intent' => $piId,
                                'reason'         => $e->getMessage(),
                            ]);
                            Refund::create(['payment_intent' => $piId]);
                            $order->setPaymentStatus(PaymentStatus::Refunded);
                            $order->setPaymentFailureReason('Stock insuffisant — remboursement automatique : ' . $e->getMessage());
                            return;
                        }
                    }

                    $order->setPaymentStatus(PaymentStatus::Paid);
                    $order->setFulfillmentStatus(FulfillmentStatus::Preparing);
                    $order->setPaidAt(new \DateTimeImmutable());
                    $order->setPaymentFailureReason(null);

                    // Renseigner le moyen de paiement s'il n'est pas encore défini
                    if ($order->getPaymentMethodNameSnapshot() === null) {
                        $pm = $this->paymentMethodRepository->findOneBy(['name' => 'stripe']);
                        if ($pm !== null) {
                            $order->setPaymentMethod($pm);
                            $order->setPaymentMethodNameSnapshot($pm->getName());
                        } else {
                            $order->setPaymentMethodNameSnapshot('Stripe');
                        }
                    }

                    $this->logger->info('Paiement confirmé', [
                        'order_id'       => $order->getId(),
                        'order_ref'      => $order->getOrderReference(),
                        'amount_cents'   => $amountReceived,
                        'payment_intent' => $piId,
                    ]);

                    // Email dispatché depuis le webhook pour garantir l'envoi
                    // même si le client ferme l'onglet. Idempotent.
                    if (!$order->isConfirmationEmailSent()) {
                        $this->messageBus->dispatch(
                            new SendOrderConfirmationEmailMessage($order->getId())
                        );
                    }
                }

                if ($event->type === 'payment_intent.payment_failed') {
                    $reason = null;
                    if (isset($pi->last_payment_error) && isset($pi->last_payment_error->message)) {
                        $reason = (string) $pi->last_payment_error->message;
                    }
                    $order->setPaymentStatus(PaymentStatus::Failed);
                    $order->setPaymentFailureReason($reason);

                    $this->logger->warning('Paiement échoué', [
                        'order_id'       => $order->getId(),
                        'order_ref'      => $order->getOrderReference(),
                        'payment_intent' => $piId,
                        'reason'         => $reason,
                    ]);
                }

                if ($event->type === 'payment_intent.canceled') {
                    $reason = isset($pi->cancellation_reason)
                        ? 'Canceled: ' . (string) $pi->cancellation_reason
                        : 'Canceled';

                    $order->setPaymentStatus(PaymentStatus::Failed);
                    $order->setPaymentFailureReason($reason);

                    $this->logger->warning('Paiement annulé', [
                        'order_id'       => $order->getId(),
                        'order_ref'      => $order->getOrderReference(),
                        'payment_intent' => $piId,
                        'reason'         => $reason,
                    ]);
                }

                if ($event->type === 'charge.refunded') {
                    $wasAlreadyRefunded = $order->getPaymentStatus() === PaymentStatus::Refunded;

                    $order->setPaymentStatus(PaymentStatus::Refunded);
                    $order->setFulfillmentStatus(FulfillmentStatus::Cancelled);

                    // Restaurer le stock uniquement si ce n'est pas un double remboursement
                    if (!$wasAlreadyRefunded) {
                        $this->stockAllocator->restoreStockForCancelledOrder($order);
                        $this->logger->info('Stock restauré suite au remboursement', [
                            'order_id'       => $order->getId(),
                            'order_ref'      => $order->getOrderReference(),
                            'payment_intent' => $piId,
                        ]);
                    }

                    $this->logger->info('Commande remboursée', [
                        'order_id'       => $order->getId(),
                        'order_ref'      => $order->getOrderReference(),
                        'payment_intent' => $piId,
                    ]);
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
