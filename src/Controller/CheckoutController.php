<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Order;
use App\Entity\OrderDetails;
use App\Enum\FulfillmentStatus;
use App\Enum\PaymentStatus;
use App\Service\CartService;
use App\Service\StripeService;
use App\Repository\OrderRepository;
use App\Repository\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CheckoutController extends AbstractController
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orderRepo,
    ) {}

    #[Route('/checkout', name: 'app_checkout', methods: ['GET'])]
    public function index(
        AddressRepository $addressRepo,
        StripeService $stripeService,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $user */
        $user = $this->getUser();

        $cart = $this->cartService->getCartDetails();

        if (!isset($cart['items']) || !\is_array($cart['items']) || \count($cart['items']) === 0) {
            return $this->redirectToRoute('app_home');
        }

        $addresses = $addressRepo->findBy(['user' => $user], ['id' => 'DESC']);
        $order = $this->createDraftOrderFromCart($user, $cart);

        return $this->render('checkout/index.html.twig', [
            'cart' => $cart,
            'orderId' => $order->getId(),
            'cart_json' => json_encode($cart, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'stripe_public_key' => $stripeService->getPublicKey(),
            'addresses' => $addresses,
            'address_api' => $this->generateUrl('api_address_create'),
            'csrf_token_address' => $this->container->get('security.csrf.token_manager')->getToken('address_api')->getValue(),
        ]);
    }

    #[Route('/stripe/payment/success', name: 'app_stripe_payment_success', methods: ['GET'])]
    public function stripePaymentSuccess(Request $request): Response
    {
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

        if ($order->getCartClearedAt() === null) {
            $this->cartService->clearCart();
            $order->markCartCleared();
            $this->em->flush();
        }

        return $this->render('payment/success.html.twig', [
            'order' => $order,
        ]);
    }

    /**
     * Crée une commande "draft" à partir du panier.
     * - 1 commande draft active par user (on écrase son contenu si elle existe)
     * - Montants en centimes
     * - Lignes recréées à chaque /checkout
     */
    private function createDraftOrderFromCart(User $user, array $cart): Order
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
        $order->setUser($user);
        $order->setFulfillmentStatus(FulfillmentStatus::Draft);

        $itemsTotalHtCents = (int) ($cart['sub_total_ht'] ?? 0);
        $taxAmountCents = isset($cart['taxe']) ? (int) $cart['taxe'] : null;
        $orderTotalTtcCents = (int) ($cart['sub_total_with_carrier'] ?? 0);
        $totalWeightGrams = (int) ($cart['total_weight_grams'] ?? 0);

        $order->setItemsTotalHtCents($itemsTotalHtCents);
        $order->setTaxAmountCents($taxAmountCents);
        $order->setOrderTotalTtcCents($orderTotalTtcCents);
        $order->setTotalWeightGrams($totalWeightGrams);

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

            $detail = new OrderDetails();
            $detail->setProductId(isset($product['id']) ? (int) $product['id'] : null);
            $detail->setProductName((string) ($product['title'] ?? ''));
            $detail->setProductDescription(
                isset($product['description'])
                    ? mb_substr((string) $product['description'], 0, 2000)
                    : null
            );
            $detail->setUnitPriceCents($unitPriceCents);
            $detail->setQuantity($qty);
            $detail->setTaxAmountCents($lineTaxCents);
            $detail->setLineTotalCents($lineTotalCents);

            $order->addOrderDetail($detail);
        }

        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }
}
