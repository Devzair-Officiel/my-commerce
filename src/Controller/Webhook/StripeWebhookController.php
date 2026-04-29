<?php

namespace App\Controller\Webhook;

use Stripe\Event;
use Stripe\Refund;
use Stripe\Webhook;
use App\Enum\PaymentStatus;
use Doctrine\DBAL\LockMode;
use Psr\Log\LoggerInterface;
use App\Enum\FulfillmentStatus;
use App\Message\NotifyAdminDisputeClosedMessage;
use App\Message\NotifyAdminDisputeMessage;
use App\Message\NotifyAdminFraudWarningMessage;
use App\Message\SendOrderConfirmationEmailMessage;
use App\Message\SendPaymentActionRequiredEmailMessage;
use App\Repository\PaymentMethodRepository;
use App\Service\StockAllocatorInterface;
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
        private readonly StockAllocatorInterface $stockAllocator,
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
            'payment_intent.requires_action',
            'charge.refunded',
            'charge.dispute.created',
            'radar.early_fraud_warning.created',
            'charge.dispute.closed',
        ];

        if (!\in_array($event->type, $handled, true)) {
            return new Response('ignored', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $obj = $event->data->object;

        // Ces événements ont l'objet Charge/Dispute/FraudWarning avec payment_intent en propriété
        $eventWithPaymentIntentRef = ['charge.refunded', 'charge.dispute.created', 'charge.dispute.closed', 'radar.early_fraud_warning.created'];
        if (\in_array($event->type, $eventWithPaymentIntentRef, true)) {
            $piId = (string) ($obj->payment_intent ?? '');
        } else {
            $piId = (string) ($obj->id ?? '');
        }

        $pi = $obj; // alias pour compatibilité avec le code existant

        if ($piId === '') {
            return new Response('Missing payment_intent id', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        try {
            $this->em->wrapInTransaction(function () use ($event, $pi, $piId, $obj): void {
                // Idempotence : persist sans flush immédiat — la contrainte unique sera vérifiée
                // au flush final. Cela évite de casser l'état de la transaction PostgreSQL.
                $this->em->persist(new StripeWebhookEvent((string) $event->id, (string) $event->type));

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

                // Ne jamais rétrograder une commande payée, sauf pour les événements post-paiement légitimes
                $allowedOnPaid = ['charge.refunded', 'charge.dispute.created', 'charge.dispute.closed', 'radar.early_fraud_warning.created'];
                if ($order->getPaymentStatus() === PaymentStatus::Paid
                    && !\in_array($event->type, $allowedOnPaid, true)
                ) {
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

                    // Guard: décrémenter le stock une seule fois même si le webhook est rejoué.
                    // Si le stock a été réservé au checkout, on le confirme simplement sans
                    // toucher aux quantités (déjà décrémentées). Sinon on décrémente maintenant
                    // (cas de migration ou commande sans réservation préalable).
                    if (!$order->isStockDecremented()) {
                        try {
                            if (!$order->isStockReserved()) {
                                $this->stockAllocator->decrementStockForPaidOrder($order);
                            }
                            $order->clearStockReservation();
                            $order->markStockDecremented();
                        } catch (\RuntimeException $e) {
                            // Stock insuffisant (ancien flux sans réservation) → remboursement auto
                            $this->logger->critical('Stock insuffisant après paiement, remboursement déclenché', [
                                'order_id'       => $order->getId(),
                                'payment_intent' => $piId,
                                'reason'         => $e->getMessage(),
                            ]);
                            try {
                                Refund::create(['payment_intent' => $piId]);
                            } catch (\Throwable $refundEx) {
                                $this->logger->critical('Impossible de créer le remboursement Stripe automatique', [
                                    'order_id'       => $order->getId(),
                                    'payment_intent' => $piId,
                                    'reason'         => $refundEx->getMessage(),
                                ]);
                                throw $refundEx;
                            }
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
                    $this->stockAllocator->releaseStockReservation($order);

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
                    $this->stockAllocator->releaseStockReservation($order);

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

                if ($event->type === 'payment_intent.requires_action') {
                    // Le client doit compléter l'authentification 3DS — on le notifie par email
                    // pour qu'il revienne finaliser son paiement.
                    // On n'envoie l'email que si la commande est toujours en attente.
                    if ($order->getPaymentStatus() === PaymentStatus::Pending) {
                        $this->messageBus->dispatch(
                            new SendPaymentActionRequiredEmailMessage(
                                orderId:         (int) $order->getId(),
                                paymentIntentId: $piId,
                            )
                        );

                        $this->logger->info('Authentification 3DS requise, email de relance dispatché', [
                            'order_id'       => $order->getId(),
                            'order_ref'      => $order->getOrderReference(),
                            'payment_intent' => $piId,
                        ]);
                    }
                }

                if ($event->type === 'charge.dispute.created') {
                    $disputeId    = (string) ($obj->id ?? '');
                    $reason       = (string) ($obj->reason ?? 'unknown');
                    $amountCents  = isset($obj->amount) ? (int) $obj->amount : (int) $order->getOrderTotalTtcCents();

                    $order->setPaymentStatus(PaymentStatus::Disputed);

                    $this->logger->critical('Chargeback reçu — action requise sous 15 jours', [
                        'order_id'       => $order->getId(),
                        'order_ref'      => $order->getOrderReference(),
                        'dispute_id'     => $disputeId,
                        'reason'         => $reason,
                        'amount_cents'   => $amountCents,
                        'payment_intent' => $piId,
                    ]);

                    $this->messageBus->dispatch(
                        new NotifyAdminDisputeMessage(
                            orderId:      (int) $order->getId(),
                            disputeId:    $disputeId,
                            reason:       $reason,
                            amountCents:  $amountCents,
                        )
                    );
                }

                if ($event->type === 'radar.early_fraud_warning.created') {
                    $fraudWarningId = (string) ($obj->id ?? '');
                    $fraudType      = (string) ($obj->fraud_type ?? 'unknown');
                    $actionable     = (bool) ($obj->actionable ?? false);

                    $this->logger->critical('Alerte fraude Stripe Radar', [
                        'order_id'         => $order->getId(),
                        'order_ref'        => $order->getOrderReference(),
                        'fraud_warning_id' => $fraudWarningId,
                        'fraud_type'       => $fraudType,
                        'actionable'       => $actionable,
                        'payment_intent'   => $piId,
                    ]);

                    $this->messageBus->dispatch(
                        new NotifyAdminFraudWarningMessage(
                            orderId:        (int) $order->getId(),
                            fraudWarningId: $fraudWarningId,
                            fraudType:      $fraudType,
                            actionable:     $actionable,
                        )
                    );
                }

                if ($event->type === 'charge.dispute.closed') {
                    $disputeId     = (string) ($obj->id ?? '');
                    $disputeStatus = (string) ($obj->status ?? 'warning_closed');

                    if ($disputeStatus === 'won') {
                        // Dispute gagnée : on restaure la commande à Paid
                        $order->setPaymentStatus(PaymentStatus::Paid);
                        $order->setPaymentFailureReason(null);
                        $this->logger->info('Dispute gagnée — commande restaurée à Paid', [
                            'order_id'       => $order->getId(),
                            'order_ref'      => $order->getOrderReference(),
                            'dispute_id'     => $disputeId,
                            'payment_intent' => $piId,
                        ]);
                    } elseif ($disputeStatus === 'lost') {
                        // Dispute perdue : l'argent est débité par la banque
                        $order->setPaymentStatus(PaymentStatus::Refunded);
                        $order->setPaymentFailureReason('Dispute perdue — montant débité par la banque.');
                        $this->logger->critical('Dispute perdue — montant débité', [
                            'order_id'       => $order->getId(),
                            'order_ref'      => $order->getOrderReference(),
                            'dispute_id'     => $disputeId,
                            'payment_intent' => $piId,
                        ]);
                    } else {
                        // warning_closed : aucun impact financier
                        $this->logger->info('Dispute clôturée sans impact financier', [
                            'order_id'       => $order->getId(),
                            'order_ref'      => $order->getOrderReference(),
                            'dispute_id'     => $disputeId,
                            'status'         => $disputeStatus,
                            'payment_intent' => $piId,
                        ]);
                    }

                    $this->messageBus->dispatch(
                        new NotifyAdminDisputeClosedMessage(
                            orderId:   (int) $order->getId(),
                            disputeId: $disputeId,
                            status:    $disputeStatus,
                        )
                    );
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
