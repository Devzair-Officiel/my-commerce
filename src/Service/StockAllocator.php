<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\Product;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class StockAllocator
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function decrementStockForPaidOrder(Order $order): void
    {
        $this->em->wrapInTransaction(function () use ($order): void {
            foreach ($order->getOrderDetails() as $line) {
                $productId = $line->getProductId();
                $qty = (int) $line->getQuantity();

                if (!$productId || $qty <= 0) {
                    // Donnée incohérente : on refuse de décrémenter.
                    throw new \RuntimeException('Ligne de commande invalide (productId/quantity).');
                }

                /** @var Product|null $product */
                $product = $this->em->find(Product::class, (int) $productId, LockMode::PESSIMISTIC_WRITE);
                if (!$product) {
                    // Produit supprimé après coup : tu choisis ta stratégie.
                    // Pour un e-commerce physique, je recommande d'échouer -> stock non gérable.
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

            $this->em->flush();
        });
    }
}
