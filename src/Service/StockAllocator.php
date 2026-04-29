<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Product;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Décrémente le stock des produits d'une commande payée de façon atomique (verrou pessimiste Doctrine).
 * Ne démarre pas de transaction et ne fait pas de flush : l'appelant en est responsable.
 */
final class StockAllocator implements StockAllocatorInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    /**
     * Décrémente le stock des produits d'une commande payée.
     *
     * IMPORTANT :
     * - Ne démarre pas de transaction.
     * - Ne fait pas de flush.
     * Le code appelant (webhook) doit exécuter ceci dans une transaction et flush/commit.
     */
    public function decrementStockForPaidOrder(Order $order): void
    {
        foreach ($order->getOrderDetails() as $line) {
            $productId = $line->getProductId();
            $qty = (int) $line->getQuantity();

            if (!$productId || $qty <= 0) {
                throw new \RuntimeException('Ligne de commande invalide (productId/quantity).');
            }

            /** @var Product|null $product */
            $product = $this->em->find(Product::class, (int) $productId, LockMode::PESSIMISTIC_WRITE);
            if (!$product) {
                throw new \RuntimeException(\sprintf('Produit #%d introuvable lors du décrément.', $productId));
            }

            $stock = (int) $product->getStock();
            if ($stock < $qty) {
                throw new \RuntimeException(\sprintf(
                    'Stock insuffisant: product #%d, demandé=%d, stock=%d',
                    $productId,
                    $qty,
                    $stock
                ));
            }

            $product->setStock($stock - $qty);
        }
    }

    /**
     * Réserve le stock au moment du checkout (avant paiement).
     * Décrémente immédiatement le stock afin qu'un autre client ne puisse plus acheter
     * les mêmes articles pendant que ce client est en cours de paiement.
     *
     * - Si le stock est déjà réservé pour cette commande, libère d'abord l'ancienne
     *   réservation (utile si le panier a changé entre deux visites du checkout).
     * - Lève RuntimeException si le stock est insuffisant → l'appelant doit rediriger
     *   l'utilisateur vers son panier.
     * - Ne flush pas : l'appelant en est responsable.
     */
    public function reserveStockForDraftOrder(Order $order): void
    {
        foreach ($order->getOrderDetails() as $line) {
            $productId = $line->getProductId();
            $qty = (int) $line->getQuantity();

            if (!$productId || $qty <= 0) {
                throw new \RuntimeException('Ligne de commande invalide (productId/quantity).');
            }

            /** @var Product|null $product */
            $product = $this->em->find(Product::class, $productId, LockMode::PESSIMISTIC_WRITE);
            if (!$product) {
                throw new \RuntimeException(\sprintf('Produit #%d introuvable lors de la réservation.', $productId));
            }

            // null = stock non géré (ex: produit numérique) → pas de réservation
            if ($product->getStock() === null) {
                continue;
            }

            $stock = (int) $product->getStock();
            if ($stock < $qty) {
                throw new \RuntimeException(\sprintf(
                    'Stock insuffisant pour "%s" : demandé=%d, disponible=%d.',
                    $product->getTitle(),
                    $qty,
                    $stock,
                ));
            }

            $product->setStock($stock - $qty);
        }

        $order->markStockReserved();
    }

    /**
     * Libère la réservation de stock (paiement échoué, annulé ou panier modifié).
     * Ne libère que si le stock a bien été réservé (guard stockReservedAt).
     * Ne flush pas.
     */
    public function releaseStockReservation(Order $order): void
    {
        if (!$order->isStockReserved()) {
            return;
        }

        foreach ($order->getOrderDetails() as $line) {
            $productId = $line->getProductId();
            $qty = (int) $line->getQuantity();

            if (!$productId || $qty <= 0) {
                continue;
            }

            /** @var Product|null $product */
            $product = $this->em->find(Product::class, $productId, LockMode::PESSIMISTIC_WRITE);
            if (!$product || $product->getStock() === null) {
                continue;
            }

            $product->setStock((int) $product->getStock() + $qty);
        }

        $order->clearStockReservation();
    }

    /**
     * Réapprovisionne le stock lorsqu'une commande payée est annulée ou remboursée.
     *
     * - Ne réapprovisionne que si le stock a bien été décrémenté (guard stockDecrementedAt).
     * - Ne démarre pas de transaction, ne flush pas.
     * - Les produits supprimés sont ignorés silencieusement (log recommandé côté appelant).
     */
    public function restoreStockForCancelledOrder(Order $order): void
    {
        if (!$order->isStockDecremented()) {
            // Stock jamais décrémenté (paiement échoué avant webhook, etc.) → rien à faire
            return;
        }

        foreach ($order->getOrderDetails() as $line) {
            $productId = $line->getProductId();
            $qty = (int) $line->getQuantity();

            if (!$productId || $qty <= 0) {
                continue;
            }

            /** @var Product|null $product */
            $product = $this->em->find(Product::class, (int) $productId, LockMode::PESSIMISTIC_WRITE);
            if (!$product) {
                // Produit supprimé → on ignore, le stock n'existe plus
                continue;
            }

            // null = stock non géré → pas de réapprovisionnement
            if ($product->getStock() === null) {
                continue;
            }

            $product->setStock((int) $product->getStock() + $qty);
        }
    }
}
