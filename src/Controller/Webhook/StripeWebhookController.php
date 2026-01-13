<?php

namespace App\Controller\Webhook;

use App\Entity\StripeWebhookEvent;
use App\Enum\PaymentStatus;
use App\Repository\OrderRepository;
use App\Service\StockAllocator;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class StripeWebhookController extends AbstractController
{
    public function __construct(
        private readonly string $stripeWebhookSecret,
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orderRepository,
        private readonly StockAllocator $stockAllocator,
    ) {}

    #[Route('/webhooks/stripe', name: 'webhook_stripe', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();
        $sigHeader = (string) $request->headers->get('Stripe-Signature', '');

        if ($sigHeader === '') {
            return new Response('Missing Stripe-Signature', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        error_log('Stripe webhook HIT at ' . (new \DateTimeImmutable())->format(DATE_ATOM));


        try {
            error_log('Stripe webhook HIT at ' . (new \DateTimeImmutable())->format(DATE_ATOM));

            /** @var Event $event */
            $event = Webhook::constructEvent($payload, $sigHeader, $this->stripeWebhookSecret);
        } catch (SignatureVerificationException) {
            return new Response('Invalid signature', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        } catch (\UnexpectedValueException) {
            return new Response('Invalid payload', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        if ($event->type !== 'payment_intent.succeeded') {
            return new Response('ignored', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        $pi = $event->data->object; // PaymentIntent (stdClass)
        $piId = (string) ($pi->id ?? '');
        if ($piId === '') {
            return new Response('Missing payment_intent id', 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        try {
            $this->em->wrapInTransaction(function () use ($event, $pi, $piId): void {
                // 1) Idempotence : on tente d'insérer l'event DANS la transaction.
                // Si doublon => exception => transaction rollback => on catch plus bas et on répond OK.
                $this->em->persist(new StripeWebhookEvent((string) $event->id, (string) $event->type));
                $this->em->flush();

                // 2) Retrouver la commande
                $order = $this->orderRepository->findOneBy(['paymentReference' => $piId]);

                if ($order === null) {
                    $orderId = (int) (($pi->metadata->order_id ?? 0));
                    if ($orderId > 0) {
                        $order = $this->orderRepository->find($orderId);
                    }
                }

                if ($order === null) {
                    // on échoue pour que Stripe retente (si tu préfères stopper retries, remplace par return + pas d’exception)
                    throw new \RuntimeException('Order not found for payment_intent.');
                }

                // 3) Lock commande pour éviter double traitement concurrent
                $this->em->lock($order, LockMode::PESSIMISTIC_WRITE);

                if ($order->getPaymentStatus() === PaymentStatus::Paid) {
                    return;
                }

                // 4) Vérif montant si dispo
                $amountReceived = isset($pi->amount_received) ? (int) $pi->amount_received : null;
                if ($amountReceived !== null && $amountReceived !== (int) $order->getOrderTotalTtcCents()) {
                    throw new \RuntimeException('Montant payé différent du total commande.');
                }

                // 5) Décrément stock (avec locks produits)
                $this->stockAllocator->decrementStockForPaidOrder($order);

                // 6) Mettre commande en Paid
                $order->setPaymentStatus(PaymentStatus::Paid);
                $order->setPaidAt(new \DateTimeImmutable());

                if (!$order->getPaymentReference()) {
                    $order->setPaymentReference($piId);
                }

                $this->em->flush();
            });
        } catch (\Throwable $e) {
            // Important : si doublon event.id => on retourne 200 (déjà traité)
            // si autre erreur => on retourne 500 pour que Stripe retente (comportement souhaitable tant que bug)
            $msg = $e->getMessage();

            // heuristique simple : si c’est une contrainte unique (doublon event), on stoppe retries
            if (str_contains($msg, 'uniq_stripe_event_id') || str_contains($msg, 'stripe_event_id')) {
                return new Response('ok', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
            }

            return new Response('error: ' . $msg, 500, ['Content-Type' => 'text/plain; charset=utf-8']);
        }

        return new Response('ok', 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
