<?php

namespace App\Controller;

use App\Service\CartService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

/**
 * Contrôleur public pour l'affichage et la gestion du panier (ajout, retrait, mise à jour de quantité).
 */
class CartController extends AbstractController
{
    public function __construct(private CartService $cartService)
    {
    }

    #[Route('/cart', name: 'app_cart')]
    public function index(): Response
    {
        $cart = $this->cartService->getCartDetails();

        return $this->render('cart/index.html.twig', [
            'cart' => $cart,
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

}
