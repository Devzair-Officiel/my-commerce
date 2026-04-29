<?php

namespace App\Controller;

use App\Entity\Order;
use App\Entity\OrderDetails;
use App\Entity\User;
use App\Enum\FulfillmentStatus;
use App\Enum\PaymentStatus;
use App\Message\SendOrderConfirmationEmailMessage;
use App\Repository\AddressRepository;
use App\Repository\CarrierRepository;
use App\Repository\OrderRepository;
use App\Service\Carrier\ColissimoClient;
use App\Service\CartService;
use App\Service\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use App\Service\StockAllocatorInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur du tunnel de commande : récapitulatif, initialisation du paiement Stripe
 * et création de la commande après confirmation du paiement.
 */
class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orderRepo,
        private readonly RequestStack $requestStack,
        private readonly LockFactory $lockFactory,
        private readonly StockAllocatorInterface $stockAllocator,
    ) {}

    #[Route('/checkout', name: 'app_checkout', methods: ['GET'])]
    public function index(
        AddressRepository $addressRepo,
        CarrierRepository $carrierRepo,
        StripeService $stripeService,
        ColissimoClient $colissimoClient,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        $cart = $this->cartService->getCartDetails();

        if (!isset($cart['items']) || !\is_array($cart['items']) || \count($cart['items']) === 0) {
            return $this->redirectToRoute('app_home');
        }

        $addresses = $addressRepo->findBy(['user' => $user], ['id' => 'DESC']);
        $carriers  = $carrierRepo->findAll();

        try {
            $order = $this->createDraftOrderFromCart($user, $cart);
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('app_cart');
        }

        // Token widget Colissimo pour la sélection de point relais
        $colissimoToken = '';
        try {
            $colissimoToken = $colissimoClient->getWidgetToken();
        } catch (\Throwable) {
            // Le widget ne sera pas disponible si le token échoue
        }

        return $this->render('checkout/index.html.twig', [
            'cart'                   => $cart,
            'orderId'                => $order->getId(),
            'cart_json'              => json_encode($cart, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'stripe_public_key'      => $stripeService->getPublicKey(),
            'colissimo_token'        => $colissimoToken,
            'addresses'              => $addresses,
            'carriers'               => $carriers,
            'address_api'            => $this->generateUrl('api_address_create'),
            'csrf_token_address'     => $this->container->get('security.csrf.token_manager')->getToken('address_api')->getValue(),
        ]);
    }

    #[Route('/stripe/payment/success', name: 'app_stripe_payment_success', methods: ['GET'])]
    public function stripePaymentSuccess(
        Request $request,
        MessageBusInterface $messageBus,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        $paymentIntentId = (string) $request->query->get('payment_intent', '');

        if ($paymentIntentId === '') {
            return $this->render('payment/pending.html.twig', [
                'page_name' => '⏳ Confirmation du paiement',
                'auto_refresh' => false,
            ]);
        }

        $order = $this->orderRepo->findOneBy([
            'user' => $user,
            'paymentReference' => $paymentIntentId,
        ]);

        if (!$order instanceof Order) {
            return $this->render('payment/pending.html.twig', [
                'page_name' => '⏳ Confirmation du paiement',
                'auto_refresh' => true,
            ]);
        }

        if (null === $order->getOrderReference()) {
            $order->generateOrderReferenceIfMissing();
        }

        // Toujours vider la session, quelle que soit la situation en base
        $this->cartService->clearCart();

        // Fallback : garantir que paidAt est toujours renseigné dès que le paiement est confirmé
        if ($order->getPaidAt() === null) {
            $order->setPaidAt(new \DateTimeImmutable());
        }

        if ($order->getCartClearedAt() === null) {
            $order->markCartCleared();
        }

        // Fallback email : si le webhook Stripe n'a pas encore envoyé l'email de confirmation
        if (!$order->isConfirmationEmailSent()) {
            $messageBus->dispatch(new SendOrderConfirmationEmailMessage($order->getId()));
        }

        $this->em->flush();

        return $this->render('payment/success.html.twig', [
            'order' => $order,
        ]);
    }

    private function createDraftOrderFromCart(User $user, array $cart): Order
    {
        // Verrou par utilisateur : empêche deux onglets/requêtes simultanées
        // de créer deux commandes brouillon pour le même panier.
        $lock = $this->lockFactory->createLock('checkout_draft_' . $user->getId(), ttl: 10);
        $lock->acquire(blocking: true);

        try {
            return $this->buildDraftOrder($user, $cart);
        } finally {
            $lock->release();
        }
    }

    private function buildDraftOrder(User $user, array $cart): Order
    {
        return $this->em->wrapInTransaction(function () use ($user, $cart): Order {
            return $this->doBuildDraftOrder($user, $cart);
        });
    }

    private function doBuildDraftOrder(User $user, array $cart): Order
    {
        $draft = $this->orderRepo->findOneBy([
            'user' => $user,
            'fulfillmentStatus' => FulfillmentStatus::Draft,
            'paymentStatus' => PaymentStatus::Pending,
        ]);

        if ($draft && $draft->getPaymentStatus() !== PaymentStatus::Pending) {
            $draft = null;
        }

        $order = $draft ?? new Order();

        // Libère l'éventuelle réservation AVANT de remplacer les lignes,
        // sinon on restaurerait les mauvaises quantités (nouvelles vs anciennes).
        if ($order->isStockReserved()) {
            $this->stockAllocator->releaseStockReservation($order);
        }

        $order->setUser($user);
        $order->setFulfillmentStatus(FulfillmentStatus::Draft);
        $order->generateOrderReferenceIfMissing();

        $itemsTotalHtCents = (int) ($cart['sub_total_ht'] ?? 0);
        $taxAmountCents = isset($cart['taxe']) ? (int) $cart['taxe'] : null;
        $orderTotalTtcCents = (int) ($cart['sub_total_with_carrier'] ?? 0);
        $totalWeightGrams = (int) ($cart['total_weight_grams'] ?? 0);

        $order->setItemsTotalHtCents($itemsTotalHtCents);
        $order->setTaxAmountCents($taxAmountCents);
        $order->setOrderTotalTtcCents($orderTotalTtcCents);
        $order->setTotalWeightGrams($totalWeightGrams);
        $order->setCurrency('EUR');

        $carrierName = (string) (($cart['carrier']['name'] ?? '') ?: '');
        $carrierPriceCents = (int) ($cart['carrier']['price'] ?? 0);

        $order->setCarrierNameSnapshot($carrierName !== '' ? $carrierName : 'Standard');
        $order->setCarrierPriceSnapshotCents($carrierPriceCents);

        if ($order->getBillingAddress() === null) {
            $order->setBillingAddress('');
        }

        if ($order->getShippingAddress() === null) {
            $order->setShippingAddress('');
        }

        foreach ($order->getOrderDetails() as $detail) {
            $order->removeOrderDetail($detail);
        }

        foreach (($cart['items'] ?? []) as $item) {
            $product = $item['product'] ?? [];
            $qty = (int) ($item['quantity'] ?? 0);

            if ($qty <= 0) {
                continue;
            }

            $unitPriceCents = (int) ($product['displayPrice'] ?? 0);
            $lineTotalCents = (int) ($item['sub_total'] ?? ($unitPriceCents * $qty));
            $lineTaxCents = isset($item['taxe']) ? (int) $item['taxe'] : null;

            $media = $product['image'][0] ?? null;
            $imageFilename = is_array($media) ? ($media['filename'] ?? null) : null;
            $imageAlt = is_array($media) ? ($media['alt'] ?? null) : null;

            $detail = new OrderDetails();
            $detail->setProductId(isset($product['id']) ? (int) $product['id'] : null);
            $detail->setProductName((string) ($product['title'] ?? ''));
            $detail->setProductDescription(
                isset($product['description'])
                    ? mb_substr((string) $product['description'], 0, 2000)
                    : null
            );
            $detail->setProductImageUrl($this->buildAbsoluteProductImageUrl(
                is_string($imageFilename) && $imageFilename !== '' ? $imageFilename : null
            ));
            $detail->setProductImageAlt(
                is_string($imageAlt) && $imageAlt !== ''
                    ? mb_substr($imageAlt, 0, 255)
                    : null
            );
            $detail->setUnitPriceCents($unitPriceCents);
            $detail->setQuantity($qty);
            $detail->setTaxAmountCents($lineTaxCents);
            $detail->setLineTotalCents($lineTotalCents);

            $order->addOrderDetail($detail);
        }

        $this->em->persist($order);

        // Réserve le stock dès le checkout : empêche un autre client d'acheter
        // les mêmes articles pendant que celui-ci est en cours de paiement.
        // Si le stock est insuffisant, une RuntimeException est levée et
        // l'utilisateur est redirigé vers son panier (voir index()).
        $this->stockAllocator->reserveStockForDraftOrder($order);

        $this->em->flush();

        return $order;
    }

    private function buildAbsoluteProductImageUrl(?string $filename): ?string
    {
        if (!$filename) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return null;
        }

        return $request->getSchemeAndHttpHost() . '/assets/images/products/' . ltrim($filename, '/');
    }
}
