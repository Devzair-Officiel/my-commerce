<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Product;
use App\Entity\User;
use App\Entity\Wishlist;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class WishlistService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProductRepository $productRepo,
        private readonly Security $security,
    ) {}

    public function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();
        return $user instanceof User ? $user : null;
    }

    public function getOrCreateWishlistForUser(User $user): Wishlist
    {
        $wishlist = $user->getWishlist();
        if ($wishlist === null) {
            // Si tu as ajouté getOrCreateWishlist() dans User, tu peux l’utiliser.
            $wishlist = new Wishlist($user);
            $user->setWishlist($wishlist);
            $this->em->persist($wishlist);
        }

        return $wishlist;
    }

    public function addProductToWishlist(User $user, int $productId): bool
    {
        $product = $this->productRepo->find($productId);
        if (!$product instanceof Product) {
            return false;
        }

        $wishlist = $this->getOrCreateWishlistForUser($user);
        $wishlist->addProduct($product);

        $this->em->flush();
        return true;
    }

    public function removeProductFromWishlist(User $user, int $productId): bool
    {
        $wishlist = $user->getWishlist();
        if ($wishlist === null) {
            return false;
        }

        $product = $this->productRepo->find($productId);
        if (!$product instanceof Product) {
            return false;
        }

        $wishlist->removeProduct($product);
        $this->em->flush();
        return true;
    }

    /**
     * Retourne la wishlist “format tableau” (comme ton code actuel)
     */
    public function getWishlistDetails(User $user): array
    {
        $wishlist = $user->getWishlist();
        if ($wishlist === null) {
            return [];
        }

        $wishlistArray = [];

        foreach ($wishlist->getProducts() as $product) {
            $wishlistArray[] = [
                'id' => $product->getId(),
                'title' => $product->getTitle(),
                'slug' => $product->getSlug(),
                'image' => $product->getMediaFilenames(),
                'soldePrice' => $product->getSoldePrice(),
                'regularPrice' => $product->getRegularPrice(),
                'stock' => $product->getStock(),
            ];
        }

        return $wishlistArray;
    }
}
