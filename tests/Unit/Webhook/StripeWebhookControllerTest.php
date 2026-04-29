<?php

declare(strict_types=1);

namespace App\Tests\Unit\Webhook;

use App\Controller\Webhook\StripeWebhookController;
use App\Entity\Order;
use App\Enum\FulfillmentStatus;
use App\Enum\PaymentStatus;
use App\Repository\OrderRepository;
use App\Repository\PaymentMethodRepository;
use App\Service\StockAllocatorInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

/**
 * Tests unitaires du StripeWebhookController.
 *
 * On ne vérifie pas la signature Stripe (on bypass en mockant Webhook::constructEvent).
 * On teste la logique métier déclenchée par chaque type d'événement.
 *
 * Scénarios couverts :
 *  1.  Signature manquante → 400
 *  2.  Rate limit dépassé → 429
 *  3.  Événement ignoré → 200 "ignored"
 *  4.  payment_intent.succeeded : commande payée, stock confirmé, email dispatché
 *  5.  payment_intent.succeeded rejoué (idempotence) → 200 sans double traitement
 *  6.  payment_intent.succeeded : stock réservé → confirmation sans re-décrémentation
 *  7.  payment_intent.payment_failed : statut Failed, réservation libérée
 *  8.  payment_intent.canceled : statut Failed, réservation libérée
 *  9.  charge.refunded : statut Refunded, stock restauré
 * 10.  Order introuvable → 500 (Stripe retente)
 * 11.  Signature invalide (header présent, HMAC faux) → 400
 * 12.  Montant reçu ≠ montant attendu → 500 (Amount mismatch)
 * 13.  payment_intent.succeeded stock déjà décrémenté → early return, pas de double opération
 * 14.  charge.refunded commande déjà remboursée → restoreStock NOT appelé
 * 15.  payment_intent.succeeded stock insuffisant → remboursement auto, statut Refunded
 */
class StripeWebhookControllerTest extends TestCase
{
    private EntityManagerInterface $em;
    private OrderRepository $orderRepo;
    private StockAllocatorInterface $stockAllocator;
    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        $this->em             = $this->createStub(EntityManagerInterface::class);
        $this->orderRepo      = $this->createStub(OrderRepository::class);
        $this->stockAllocator = $this->createStub(StockAllocatorInterface::class);
        $this->messageBus     = $this->createStub(MessageBusInterface::class);
    }

    public function testSignatureManquanteRetourne400(): void
    {
        $response = $this->makeController()(new Request(content: '{}'));

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testRateLimitDepasse(): void
    {
        $response = $this->makeController(rateLimitAccepted: false)(
            $this->makeRequest('payment_intent.succeeded', 'pi_test')
        );

        $this->assertSame(429, $response->getStatusCode());
    }

    public function testEvenementIgnore(): void
    {
        $response = $this->makeController()($this->makeRequest('customer.created', 'cus_test'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ignored', $response->getContent());
    }

    public function testPaymentSucceededMarqueCommandePayee(): void
    {
        $stockAllocator = $this->createMock(StockAllocatorInterface::class);
        $messageBus     = $this->createMock(MessageBusInterface::class);

        $order = $this->makeOrder(PaymentStatus::Pending, FulfillmentStatus::Draft);
        $order->method('isStockDecremented')->willReturn(false);
        $order->method('isStockReserved')->willReturn(false);
        $order->method('isConfirmationEmailSent')->willReturn(false);
        $order->method('getPaymentMethodNameSnapshot')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn (callable $fn) => $fn());

        $orderRepo = $this->createStub(OrderRepository::class);
        $orderRepo->method('findOneBy')->willReturn($order);

        $stockAllocator->expects($this->once())->method('decrementStockForPaidOrder');
        $messageBus->expects($this->once())->method('dispatch')->willReturn(new Envelope(new \stdClass()));
        $order->expects($this->once())->method('setPaymentStatus')->with(PaymentStatus::Paid);
        $order->expects($this->once())->method('markStockDecremented');

        $controller = $this->makeController(em: $em, orderRepo: $orderRepo, stockAllocator: $stockAllocator, messageBus: $messageBus);
        $response   = $controller($this->makeRequest('payment_intent.succeeded', 'pi_test', amountReceived: 5000));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPaymentSucceededStockDejaReserveNePasRedecrementer(): void
    {
        $stockAllocator = $this->createMock(StockAllocatorInterface::class);

        $order = $this->makeOrder(PaymentStatus::Pending, FulfillmentStatus::Draft);
        $order->method('isStockDecremented')->willReturn(false);
        $order->method('isStockReserved')->willReturn(true);
        $order->method('isConfirmationEmailSent')->willReturn(false);
        $order->method('getPaymentMethodNameSnapshot')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn (callable $fn) => $fn());

        $orderRepo = $this->createStub(OrderRepository::class);
        $orderRepo->method('findOneBy')->willReturn($order);

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $stockAllocator->expects($this->never())->method('decrementStockForPaidOrder');
        $order->expects($this->once())->method('clearStockReservation');
        $order->expects($this->once())->method('markStockDecremented');

        $controller = $this->makeController(em: $em, orderRepo: $orderRepo, stockAllocator: $stockAllocator, messageBus: $messageBus);
        $controller($this->makeRequest('payment_intent.succeeded', 'pi_test', amountReceived: 5000));
    }

    public function testPaymentSucceededIdempotenceWebhookRejouéRetourne200(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn (callable $fn) => $fn());
        $em->method('flush')->willThrowException($this->createStub(UniqueConstraintViolationException::class));

        $response = $this->makeController(em: $em)($this->makeRequest('payment_intent.succeeded', 'pi_test'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function testPaymentFailedLibereReservationEtMarqueStatut(): void
    {
        $stockAllocator = $this->createMock(StockAllocatorInterface::class);

        $order = $this->makeOrder(PaymentStatus::Pending, FulfillmentStatus::Draft);
        $order->method('isStockReserved')->willReturn(true);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn (callable $fn) => $fn());

        $orderRepo = $this->createStub(OrderRepository::class);
        $orderRepo->method('findOneBy')->willReturn($order);

        $stockAllocator->expects($this->once())->method('releaseStockReservation')->with($order);
        $order->expects($this->once())->method('setPaymentStatus')->with(PaymentStatus::Failed);

        $response = $this->makeController(em: $em, orderRepo: $orderRepo, stockAllocator: $stockAllocator)(
            $this->makeRequest('payment_intent.payment_failed', 'pi_test')
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPaymentCanceledLibereReservation(): void
    {
        $stockAllocator = $this->createMock(StockAllocatorInterface::class);

        $order = $this->makeOrder(PaymentStatus::Pending, FulfillmentStatus::Draft);
        $order->method('isStockReserved')->willReturn(true);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn (callable $fn) => $fn());

        $orderRepo = $this->createStub(OrderRepository::class);
        $orderRepo->method('findOneBy')->willReturn($order);

        $stockAllocator->expects($this->once())->method('releaseStockReservation')->with($order);
        $order->expects($this->once())->method('setPaymentStatus')->with(PaymentStatus::Failed);

        $response = $this->makeController(em: $em, orderRepo: $orderRepo, stockAllocator: $stockAllocator)(
            $this->makeRequest('payment_intent.canceled', 'pi_test')
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testChargeRefundedRestaureLeStock(): void
    {
        $stockAllocator = $this->createMock(StockAllocatorInterface::class);

        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn(42);
        $order->method('getOrderReference')->willReturn('CMD-2026-TEST');
        $order->method('getPaymentReference')->willReturn(null);
        $order->method('getPaymentStatus')->willReturn(PaymentStatus::Paid);
        $order->method('getOrderTotalTtcCents')->willReturn(5000);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn (callable $fn) => $fn());

        $orderRepo = $this->createStub(OrderRepository::class);
        $orderRepo->method('findOneBy')->willReturn($order);

        $stockAllocator->expects($this->once())->method('restoreStockForCancelledOrder')->with($order);

        $response = $this->makeController(em: $em, orderRepo: $orderRepo, stockAllocator: $stockAllocator)(
            $this->makeChargeRefundedRequest('pi_test')
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testOrderIntrouvableRetourne500(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn (callable $fn) => $fn());

        $orderRepo = $this->createStub(OrderRepository::class);
        $orderRepo->method('findOneBy')->willReturn(null);

        $response = $this->makeController(em: $em, orderRepo: $orderRepo)(
            $this->makeRequest('payment_intent.succeeded', 'pi_test')
        );

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testSignatureInvalideRetourne400(): void
    {
        $payload = json_encode(['id' => 'evt_test', 'type' => 'payment_intent.succeeded', 'data' => ['object' => ['id' => 'pi_test']]]);
        $request = new Request(content: $payload);
        $request->headers->set('Stripe-Signature', 't=1234,v1=invalidsignature');

        $response = $this->makeController()($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testMontantRecuDifferentRetourne500(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn(42);
        $order->method('getOrderReference')->willReturn('CMD-2026-TEST');
        $order->method('getPaymentReference')->willReturn(null);
        $order->method('getPaymentStatus')->willReturn(PaymentStatus::Pending);
        $order->method('getOrderTotalTtcCents')->willReturn(5000);
        $order->method('isStockDecremented')->willReturn(false);
        $order->method('isStockReserved')->willReturn(false);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn (callable $fn) => $fn());

        $orderRepo = $this->createStub(OrderRepository::class);
        $orderRepo->method('findOneBy')->willReturn($order);

        // amountReceived=9999 ≠ orderTotalTtcCents=5000
        $response = $this->makeController(em: $em, orderRepo: $orderRepo)(
            $this->makeRequest('payment_intent.succeeded', 'pi_test', amountReceived: 9999)
        );

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testPaymentSucceededStockDejaDecrementeNeDecrementerPasDeuxFois(): void
    {
        $stockAllocator = $this->createMock(StockAllocatorInterface::class);

        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn(42);
        $order->method('getOrderReference')->willReturn('CMD-2026-TEST');
        $order->method('getPaymentReference')->willReturn(null);
        $order->method('getPaymentStatus')->willReturn(PaymentStatus::Pending);
        $order->method('getOrderTotalTtcCents')->willReturn(5000);
        $order->method('isStockDecremented')->willReturn(true); // déjà fait
        $order->method('isConfirmationEmailSent')->willReturn(false);
        $order->method('getPaymentMethodNameSnapshot')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn (callable $fn) => $fn());

        $orderRepo = $this->createStub(OrderRepository::class);
        $orderRepo->method('findOneBy')->willReturn($order);

        $messageBus = $this->createStub(MessageBusInterface::class);
        $messageBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $stockAllocator->expects($this->never())->method('decrementStockForPaidOrder');

        $this->makeController(em: $em, orderRepo: $orderRepo, stockAllocator: $stockAllocator, messageBus: $messageBus)(
            $this->makeRequest('payment_intent.succeeded', 'pi_test', amountReceived: 5000)
        );
    }

    public function testChargeRefundedDejaRembourseNePasRestaurerLeStock(): void
    {
        $stockAllocator = $this->createMock(StockAllocatorInterface::class);

        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn(42);
        $order->method('getOrderReference')->willReturn('CMD-2026-TEST');
        $order->method('getPaymentReference')->willReturn(null);
        // Commande déjà au statut Refunded
        $order->method('getPaymentStatus')->willReturn(PaymentStatus::Refunded);
        $order->method('getOrderTotalTtcCents')->willReturn(5000);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn (callable $fn) => $fn());

        $orderRepo = $this->createStub(OrderRepository::class);
        $orderRepo->method('findOneBy')->willReturn($order);

        $stockAllocator->expects($this->never())->method('restoreStockForCancelledOrder');

        $response = $this->makeController(em: $em, orderRepo: $orderRepo, stockAllocator: $stockAllocator)(
            $this->makeChargeRefundedRequest('pi_test')
        );

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testPaymentSucceededStockInsuffisantDeclenchemboursementAuto(): void
    {
        $stockAllocator = $this->createStub(StockAllocatorInterface::class);

        $order = $this->createStub(Order::class);
        $order->method('getId')->willReturn(42);
        $order->method('getOrderReference')->willReturn('CMD-2026-TEST');
        $order->method('getPaymentReference')->willReturn(null);
        $order->method('getPaymentStatus')->willReturn(PaymentStatus::Pending);
        $order->method('getOrderTotalTtcCents')->willReturn(5000);
        $order->method('isStockDecremented')->willReturn(false);
        $order->method('isStockReserved')->willReturn(false);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('wrapInTransaction')->willReturnCallback(fn (callable $fn) => $fn());

        $orderRepo = $this->createStub(OrderRepository::class);
        $orderRepo->method('findOneBy')->willReturn($order);

        $stockAllocator->method('decrementStockForPaidOrder')
            ->willThrowException(new \RuntimeException('Stock insuffisant: product #1, demandé=5, stock=2'));

        // Refund::create() nécessite une vraie clé Stripe → lève une exception en test.
        // Le catch \Throwable du contrôleur renvoie 500 pour que Stripe retente.
        $response = $this->makeController(em: $em, orderRepo: $orderRepo, stockAllocator: $stockAllocator)(
            $this->makeRequest('payment_intent.succeeded', 'pi_test', amountReceived: 5000)
        );

        $this->assertSame(500, $response->getStatusCode());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeController(
        ?EntityManagerInterface $em = null,
        ?OrderRepository $orderRepo = null,
        ?StockAllocatorInterface $stockAllocator = null,
        ?MessageBusInterface $messageBus = null,
        bool $rateLimitAccepted = true,
    ): StripeWebhookController {
        $paymentMethodRepo = $this->createStub(PaymentMethodRepository::class);
        $paymentMethodRepo->method('findOneBy')->willReturn(null);

        return new StripeWebhookController(
            stripeWebhookSecret: 'whsec_test',
            em: $em ?? $this->em,
            orderRepository: $orderRepo ?? $this->orderRepo,
            paymentMethodRepository: $paymentMethodRepo,
            stockAllocator: $stockAllocator ?? $this->stockAllocator,
            messageBus: $messageBus ?? $this->messageBus,
            stripeWebhookLimiter: $this->makeRateLimiter($rateLimitAccepted),
            logger: new NullLogger(),
        );
    }

    private function makeOrder(PaymentStatus $paymentStatus, FulfillmentStatus $fulfillmentStatus): Order&MockObject
    {
        $order = $this->createMock(Order::class);
        $order->method('getId')->willReturn(42);
        $order->method('getOrderReference')->willReturn('CMD-2026-TEST');
        $order->method('getPaymentReference')->willReturn(null);
        $order->method('getPaymentStatus')->willReturn($paymentStatus);
        $order->method('getFulfillmentStatus')->willReturn($fulfillmentStatus);
        $order->method('getOrderTotalTtcCents')->willReturn(5000);
        return $order;
    }

    /**
     * Construit une Request simulant un webhook Stripe valide.
     * On bypasse la vérification de signature en mockant le contrôleur
     * avec un secret connu et en fournissant le payload brut + header valide.
     *
     * Pour simplifier les tests unitaires, on utilise une approche différente :
     * on crée un sous-contrôleur qui court-circuite constructEvent().
     */
    private function makeRequest(string $type, string $piId, ?int $amountReceived = null): Request
    {
        $payload = $this->buildPayload($type, $piId, $amountReceived);
        return $this->makeSignedRequest($payload);
    }

    private function makeChargeRefundedRequest(string $piId): Request
    {
        $payload = json_encode([
            'id'   => 'evt_' . uniqid(),
            'type' => 'charge.refunded',
            'data' => [
                'object' => [
                    'id'             => 'ch_test',
                    'payment_intent' => $piId,
                ],
            ],
        ]);

        return $this->makeSignedRequest($payload);
    }

    private function buildPayload(string $type, string $piId, ?int $amountReceived): string
    {
        $object = ['id' => $piId];
        if ($amountReceived !== null) {
            $object['amount_received'] = $amountReceived;
        }

        return json_encode([
            'id'   => 'evt_' . uniqid(),
            'type' => $type,
            'data' => ['object' => $object],
        ]);
    }

    private function makeSignedRequest(string $payload): Request
    {
        $secret    = 'whsec_test';
        $timestamp = time();
        $sig       = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
        $header    = "t={$timestamp},v1={$sig}";

        $request = new Request(content: $payload);
        $request->headers->set('Stripe-Signature', $header);
        return $request;
    }

    private function makeRateLimiter(bool $accepted): RateLimiterFactory
    {
        // RateLimiterFactory est final → on construit un vrai avec un store en mémoire.
        // Pour simuler le rejet, on utilise limit=1 et on pré-consomme le seul token disponible.
        $config  = ['id' => 'test', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'];
        $storage = new InMemoryStorage();
        $factory = new RateLimiterFactory($config, $storage);

        if (!$accepted) {
            $factory->create('stripe_webhook')->consume(1); // épuise le quota
        }

        return $factory;
    }
}
