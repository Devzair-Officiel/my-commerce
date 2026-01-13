<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Product;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

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
}
