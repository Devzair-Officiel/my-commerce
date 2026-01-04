<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Order;
use App\Entity\OrderDetails;
use App\Service\CartService;
use App\Service\PaypalService;
use App\Services\StripeService;
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
    public function __construct(private CartService $cartService, RequestStack $requestStack, private EntityManagerInterface $em)
    {
        $this->cartService = $cartService;
        $this->session = $requestStack->getSession();
    }

    #[Route('/checkout', name: 'app_checkout')]
    public function index(): Response
    {
        $cart = $this->cartService->getCartDetails();

        if (!count($cart["items"])) {
            return $this->redirectToRoute('app_home');
        }

        $user = $this->getUser();

        if (!$user) {
            $this->session->set("next", [
                'route' => 'app_checkout',
                'params' => []
            ]);
            return $this->redirectToRoute('app_login');
        }

        // $addresses = $addressRepo->findBy(['user' => $user]);
        $cart_json = json_encode($cart);

        // $orderId = $this->createOrder($cart);

        // $stripe_Public_Key = $stripeService->getPublicKey();
        // $paypal_Public_Key = $paypalService->getPublicKey();

        return $this->render('checkout/index.html.twig', [
            'controller_name' => 'CheckoutController',
            'cart' => $cart,
            // 'orderId' => $orderId,
            'cart_json' => $cart_json,
            // 'stripe_public_key' => $stripe_Public_Key,
            // 'paypal_public_key' => $paypal_Public_Key,
            // 'addresses' => $addresses,
        ]);
    }

    // #[Route('/stripe/payment/success', name: 'app_stripe_payment_success')]
    // public function paymentSuccess(Request $request, OrderRepository $orderRepo, EntityManagerInterface $em, MailerInterface $mailer): Response
    // {
    //     $stripeClientSecret = $request->query->get("payment_intent_client_secret");

    //     $order = $orderRepo->findOneBy(['stripeClientSecret' => $stripeClientSecret]);
    //     $user = $this->getUser();

    //     if (!$order) {
    //         return $this->redirectToRoute('app_error');
    //     }

    //     $this->cartService->update("cart", []);
    //     $order->setIsPaid(true);

    //     $em->persist($order);
    //     $em->flush();


    //     if ($user instanceof User && $order->isIsPaid() == true) {
    //         // Envoi de l'e-mail de confirmation
    //         $email = (new Email())
    //             ->from('contact@nidemiel.com')
    //             ->to($user->getEmail())
    //             ->subject('Confirmation de paiement')
    //             ->html($this->renderView('payment/email_payment_success.html.twig', [
    //                 'order' => $order,
    //                 'user' => $user
    //             ]));

    //         $mailer->send($email);
    //     }

    //     return $this->render('payment/index.html.twig', [
    //         'controller_name' => 'PaymentController',
    //     ]);
    // }

    // #[Route('/paypal/payment/success', name: 'app_paypal_payment_success')]
    // public function paypalPaymentSuccess(Request $request, OrderRepository $orderRepo, EntityManagerInterface $em, MailerInterface $mailer)
    // {
    //     $paypalClientSecret = $request->query->get("payment_intent_client_secret");

    //     $order = $orderRepo->findOneBy(['paypalClientSecret' => $paypalClientSecret]);
    //     $user = $this->getUser();

    //     if (!$order) {
    //         return $this->redirectToRoute('app_error');
    //     }

    //     $this->cartService->update("cart", []);
    //     $order->setIsPaid(true);

    //     $em->persist($order);
    //     $em->flush();


    //     if ($user instanceof User && $order->isIsPaid() == true) {
    //         // Envoi de l'e-mail de confirmation
    //         $email = (new Email())
    //             ->from('contact@nidemiel.com')
    //             ->to($user->getEmail())
    //             ->subject('Confirmation de paiement')
    //             ->html($this->renderView('payment/email_payment_success.html.twig', [
    //                 'order' => $order,
    //                 'user' => $user
    //             ]));

    //         $mailer->send($email);
    //     }

    //     return $this->render('payment/index.html.twig', [
    //         'controller_name' => 'PaymentController',
    //     ]);
    // }


    // public function createOrder($cart)
    // {
    //     $user = $this->getUser();

    //     if ($user instanceof User) {

    //         $order = $this->orderRepo->findOneBy([
    //             "client_name" => $user->getFirstname() . " " . $user->getLastname(),
    //             "order_cost_ht" => $cart["sub_total_ht"],
    //             "isPaid" => false,
    //             "taxe" => $cart["taxe"],
    //             "order_cost_ttc" => $cart["sub_total_with_carrier"],
    //             "carrier_name" => $cart["carrier"]["name"],
    //             "carrier_price" => $cart["carrier"]["price"],
    //             "carrier_id" => $cart["carrier"]["id"],
    //             "quantity" => $cart["quantity"],
    //         ]);
    //     }

    //     if (!$order) {
    //         $order = new Order();
    //     }

    //     if ($user instanceof User) {
    //         $order = new Order();
    //         $order->setClientName($user->getFirstname() . " " . $user->getLastname())
    //             ->setBillingAddress("")
    //             ->setShippingAddress("")
    //             ->setOrderCostHt($cart["sub_total_ht"])
    //             ->setTaxe($cart["taxe"])
    //             ->setOrderCostTtc($cart["sub_total_with_carrier"])
    //             ->setCarrierName($cart["carrier"]["name"])
    //             ->setCarrierPrice($cart["carrier"]["price"])
    //             ->setQuantity($cart["quantity"])
    //             ->setCarrierId($cart["carrier"]["id"])
    //             ->setIsPaid(false)
    //             ->setStatus("En cours");
    //         $this->em->persist($order);
    //     }


    //     foreach ($cart["items"] as $key => $item) {
    //         $product = $item["product"];
    //         $orderDetails = new OrderDetails();
    //         $orderDetails->setProductName($product["title"])
    //             ->setProductDescription(substr($product["description"], 0, 200))
    //             ->setProductSoldePrice($product["soldePrice"])
    //             ->setProductRegularPrice($product["regularPrice"])
    //             ->setQuantity($item["quantity"])
    //             ->setSubtotal($item["sub_total"])
    //             ->setTaxe($item["taxe"])
    //             ->setMyOrder($order);
    //         $this->em->persist($orderDetails);
    //     }
    //     $this->em->flush();

    //     return $order->getId();
    // }
}
