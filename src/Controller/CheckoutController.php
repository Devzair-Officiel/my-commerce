<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Order;
use App\Entity\OrderDetails;
use App\Enum\FulfillmentStatus;
use App\Enum\PaymentStatus;
use App\Service\CartService;
use App\Service\PaypalService;
use App\Service\StripeService;
use Symfony\Component\Mime\Email;
use App\Repository\OrderRepository;
use App\Repository\AddressRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CheckoutController extends AbstractController
{
    private $session;

    public function __construct(
        private readonly CartService $cartService,
        RequestStack $requestStack,
        private readonly EntityManagerInterface $em,
        private readonly OrderRepository $orderRepo,
    ) {
        $this->session = $requestStack->getSession();
    }

    #[Route('/checkout', name: 'app_checkout', methods: ['GET'])]
    public function index(
        AddressRepository $addressRepo,
        StripeService $stripeService,
        PaypalService $paypalService,
    ): Response {
        $cart = $this->cartService->getCartDetails();

        if (!isset($cart['items']) || !\is_array($cart['items']) || count($cart['items']) === 0) {
            return $this->redirectToRoute('app_home');
        }

        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            // fallback (normalement denyAccessUnlessGranted suffit)
            $this->session->set('next', ['route' => 'app_checkout', 'params' => []]);
            return $this->redirectToRoute('app_login');
        }

        $addresses = $addressRepo->findBy(['user' => $user], ['id' => 'DESC']);

        $order = $this->createDraftOrderFromCart($user, $cart);

        return $this->render('checkout/index.html.twig', [
            'cart' => $cart,
            'orderId' => $order->getId(),
            'cart_json' => json_encode($cart, JSON_UNESCAPED_UNICODE),
            'stripe_public_key' => $stripeService->getPublicKey(),
            'paypal_public_key' => $paypalService->getPublicKey(),
            'addresses' => $addresses,
        ]);
    }

    #[Route('/stripe/payment/success', name: 'app_stripe_payment_success', methods: ['GET'])]
    public function stripePaymentSuccess(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $order = $this->orderRepo->findOneBy(
            [
                'user' => $user,
                'paymentStatus' => PaymentStatus::Paid,
            ],
            ['id' => 'DESC']
        );

        if ($order) {
            $this->cartService->clearCart(); // idéalement remove + save, comme discuté
            if ($order->getCartClearedAt() === null) {
                $order->markCartCleared();
                $this->em->flush();
            }
        }

        return $this->render('payment/success.html.twig');
    }


    #[Route('/paypal/payment/success', name: 'app_paypal_payment_success', methods: ['GET'])]
    public function paypalPaymentSuccess(
        Request $request,
        OrderRepository $orderRepo,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        // ⚠️ Pour PayPal aussi, la confirmation fiable vient de la capture côté serveur / webhook.
        // Ici on garde ton flow "success" mais on sécurise au minimum.
        $paypalRef = (string) $request->query->get('payment_intent_client_secret', '');
        if ($paypalRef === '') {
            return $this->redirectToRoute('app_error');
        }

        $order = $orderRepo->findOneBy([
            'user' => $user,
            'paymentReference' => $paypalRef,
        ]);

        if (!$order instanceof Order) {
            return $this->redirectToRoute('app_error');
        }

        if ($order->getPaymentStatus() !== PaymentStatus::Paid) {
            $order->setPaymentStatus(PaymentStatus::Paid);
            $order->setPaidAt(new \DateTimeImmutable());
            $em->flush();
        }

        $this->cartService->update('cart', []);

        $email = (new Email())
            ->from('contact@nidemiel.com')
            ->to($user->getEmail())
            ->subject('Confirmation de paiement')
            ->html($this->renderView('payment/email_payment_success.html.twig', [
                'order' => $order,
                'user' => $user,
            ]));

        $mailer->send($email);

        return $this->render('payment/index.html.twig');
    }

    /**
     * Crée une commande "draft" à partir du panier.
     * - 1 commande draft active par user (on écrase son contenu si elle existe)
     * - Montants en centimes
     * - Lignes recréées à chaque /checkout (simple tant que tu es en dev)
     */
    private function createDraftOrderFromCart(User $user, array $cart): Order
    {
        $draft = $this->orderRepo->findOneBy([
            'user' => $user,
            'paymentStatus' => FulfillmentStatus::Draft,
        ]);

        $order = $draft ?? new Order();
        $order->setUser($user);
        $order->setFulfillmentStatus(FulfillmentStatus::Draft);

        // IMPORTANT: adapte ces clés à ton CartService.
        // Ici, j'assume que tes montants sont déjà en centimes.
        // Si chez toi c'est en euros float, il faut convertir proprement avant.
        $itemsTotalHtCents = (int) ($cart['sub_total_ht'] ?? 0);
        $taxAmountCents = isset($cart['taxe']) ? (int) $cart['taxe'] : null;
        $orderTotalTtcCents = (int) ($cart['sub_total_with_carrier'] ?? 0);

        $order->setItemsTotalHtCents($itemsTotalHtCents);
        $order->setTaxAmountCents($taxAmountCents);
        $order->setOrderTotalTtcCents($orderTotalTtcCents);

        // Carrier snapshot (relation Carrier optionnelle si tu l'as mise en place)
        $carrierName = (string) (($cart['carrier']['name'] ?? '') ?: '');
        $carrierPriceCents = (int) ($cart['carrier']['price'] ?? 0);

        $order->setCarrierNameSnapshot($carrierName !== '' ? $carrierName : 'Standard');
        $order->setCarrierPriceSnapshotCents($carrierPriceCents);

        // adresses non encore choisies sur /checkout (seront PATCH via /api/order/{id})
        if ($order->getBillingAddress() === null) {
            $order->setBillingAddress('');
        }
        if ($order->getShippingAddress() === null) {
            $order->setShippingAddress('');
        }

        // Nettoyage des lignes existantes si on réutilise un draft
        foreach ($order->getOrderDetails() as $detail) {
            $order->removeOrderDetail($detail);
        }

        // Recréation lignes à partir du panier
        foreach (($cart['items'] ?? []) as $item) {
            $product = $item['product'] ?? [];
            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $unitPriceCents = (int) ($product['soldePrice'] ?? $product['regularPrice'] ?? 0);
            $lineTotalCents = (int) ($item['sub_total'] ?? ($unitPriceCents * $qty));
            $lineTaxCents = isset($item['taxe']) ? (int) $item['taxe'] : null;

            $detail = new OrderDetails();
            $detail->setProductId(isset($product['id']) ? (int) $product['id'] : null);
            $detail->setProductName((string) ($product['title'] ?? ''));
            $detail->setProductDescription(isset($product['description']) ? mb_substr((string) $product['description'], 0, 2000) : null);
            $detail->setUnitPriceCents($unitPriceCents);
            $detail->setQuantity($qty);
            $detail->setTaxAmountCents($lineTaxCents);
            $detail->setLineTotalCents($lineTotalCents);

            $order->addOrderDetail($detail); // maintient la relation
        }

        $this->em->persist($order);
        $this->em->flush();

        return $order;
    }
}
