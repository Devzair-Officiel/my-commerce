<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\WishlistService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur public de la liste de souhaits : affichage, ajout et retrait de produits (utilisateur connecté requis).
 */
class WishlistController extends AbstractController
{
    public function __construct(private readonly WishlistService $wishlistService) {}

    #[Route('/wishlist', name: 'app_wish_list', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->wishlistService->getCurrentUser();
        if ($user === null) {
            return $this->redirectToRoute('app_login');
        }

        $wishlist = $this->wishlistService->getWishlistDetails($user);

        return $this->render('wishlist/index.html.twig', [
            'wishlist' => $wishlist,
        ]);
    }

    #[Route('/wishlist/add/{productId}', name: 'app_add_to_wishlist', methods: ['POST'])]
    public function addToWishlist(int $productId): Response
    {
        $user = $this->wishlistService->getCurrentUser();
        if ($user === null) {
            return $this->json(['message' => 'Non connecté'], 403);
        }

        $ok = $this->wishlistService->addProductToWishlist($user, $productId);
        if (!$ok) {
            return $this->json(['message' => 'Produit introuvable'], 404);
        }

        return $this->json($this->wishlistService->getWishlistDetails($user));
    }

    #[Route('/wishlist/remove/{productId}', name: 'app_remove_to_wishlist', methods: ['DELETE'])]
    public function removeToWishlist(int $productId): Response
    {
        $user = $this->wishlistService->getCurrentUser();
        if ($user === null) {
            return $this->json(['message' => 'Non connecté'], 403);
        }

        $this->wishlistService->removeProductFromWishlist($user, $productId);

        return $this->json($this->wishlistService->getWishlistDetails($user));
    }

    #[Route('/wishlist/get', name: 'app_get_wishlist', methods: ['GET'])]
    public function getWishlist(): Response
    {
        $user = $this->wishlistService->getCurrentUser();
        if ($user === null) {
            return $this->json(['message' => 'Non connecté'], 403);
        }

        return $this->json($this->wishlistService->getWishlistDetails($user));
    }
}
