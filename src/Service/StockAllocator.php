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
final class StockAllocator
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
                throw new \RuntimeException(sprintf('Produit #%d introuvable lors du décrément.', $productId));
            }

            $stock = (int) $product->getStock();
            if ($stock < $qty) {
                throw new \RuntimeException(sprintf(
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
