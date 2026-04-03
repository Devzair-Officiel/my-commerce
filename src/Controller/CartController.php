<?php

namespace App\Controller;

use App\Service\CartService;
use App\Repository\CarrierRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CartController extends AbstractController
{
    public function __construct(private CartService $cartService, private CarrierRepository $carrierRepo)
    {
    }

    #[Route('/cart', name: 'app_cart')]
    public function index(): Response
    {
        $cart = $this->cartService->getCartDetails();
        $carriers = $this->carrierRepo->findAll();

        foreach ($carriers as $key => $carrier) {
            $carriers[$key] = [
                "id" => $carrier->getId(),
                "name" => $carrier->getName(),
                "description" => $carrier->getDescription(),
                "price" => $carrier->getPrice(),
            ];
        }

        $cart_json = json_encode($cart);
        $carriers_json = json_encode($carriers);

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
            'carriers' => $carriers,
        ]);
    }

    #[Route('/cart/add/{productId}/{count}', name: 'app_add_cart')]
    public function addToCart(int $productId, int $count = 1): Response
    {
        try {
            $this->cartService->addToCart($productId, $count);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $cart = $this->cartService->getCartDetails();
        return $this->json($cart);
    }

    #[Route('/cart/remove/{productId}/{count}', name: 'app_remove_cart')]
    public function removeToCart(int $productId, int $count = 1): Response
    {
        $this->cartService->removeToCart($productId, $count);
        $cart = $this->cartService->getCartDetails();
        return $this->json($cart);
    }

    #[Route('/cart/get', name: 'app_get_cart')]
    public function getCart(): Response
    {
        $cart = $this->cartService->getCartDetails();
        return $this->json($cart);
    }

    #[Route('/cart/carrier', name: 'app_update_cart_carrier', methods: ["POST"])]
    public function updateCartCarrier(Request $requst): Response
    {
        $id = $requst->getPayload()->get("carrierId");

        $carrier = $this->carrierRepo->findOneById($id);

        if (!$carrier) {
            return $this->redirectToRoute("app_home");
        }

        $this->cartService->update("carrier", [
            "id" => $carrier->getId(),
            "name" => $carrier->getName(),
            "description" => $carrier->getDescription(),
            "price" => $carrier->getPrice(),
        ]);

        return $this->redirectToRoute("app_cart");
    }
}
