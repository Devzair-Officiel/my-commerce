<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Order;
use App\Entity\OrderDetails;
use App\Entity\Product;
use App\Service\StockAllocator;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour StockAllocator.
 *
 * Ce que l'on teste :
 *  - Décrémentation normale du stock
 *  - Commande avec plusieurs lignes
 *  - Stock insuffisant → exception
 *  - Ligne invalide (productId null ou qty = 0) → exception
 *  - Produit introuvable → exception
 */
class StockAllocatorTest extends TestCase
{
    private StockAllocator $allocator;

    private EntityManagerInterface&Stub $em;

    protected function setUp(): void
    {
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->allocator = new StockAllocator($this->em);
    }

    public function testDecrementStockNormal(): void
    {
        $product = $this->makeProduct(id: 1, stock: 10);

        $this->em->method('find')->willReturn($product);

        $order = $this->makeOrder([
            $this->makeLine(productId: 1, qty: 3),
        ]);

        $this->allocator->decrementStockForPaidOrder($order);

        $this->assertSame(7, $product->getStock());
    }

    public function testDecrementStockPlusieursLignes(): void
    {
        $product1 = $this->makeProduct(id: 1, stock: 10);
        $product2 = $this->makeProduct(id: 2, stock: 5);

        $this->em->method('find')->willReturnMap([
            [Product::class, 1, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE, $product1],
            [Product::class, 2, \Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE, $product2],
        ]);

        $order = $this->makeOrder([
            $this->makeLine(productId: 1, qty: 4),
            $this->makeLine(productId: 2, qty: 5),
        ]);

        $this->allocator->decrementStockForPaidOrder($order);

        $this->assertSame(6, $product1->getStock());
        $this->assertSame(0, $product2->getStock());
    }

    public function testDecrementStockExactementEpuise(): void
    {
        $product = $this->makeProduct(id: 1, stock: 3);
        $this->em->method('find')->willReturn($product);

        $order = $this->makeOrder([
            $this->makeLine(productId: 1, qty: 3),
        ]);

        $this->allocator->decrementStockForPaidOrder($order);

        $this->assertSame(0, $product->getStock());
    }

    public function testDecrementStockInsuffisantLeveException(): void
    {
        $product = $this->makeProduct(id: 1, stock: 2);
        $this->em->method('find')->willReturn($product);

        $order = $this->makeOrder([
            $this->makeLine(productId: 1, qty: 3),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stock insuffisant');

        $this->allocator->decrementStockForPaidOrder($order);
    }

    public function testDecrementProduitIntrouvableLeveException(): void
    {
        $this->em->method('find')->willReturn(null);

        $order = $this->makeOrder([
            $this->makeLine(productId: 99, qty: 1),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('introuvable');

        $this->allocator->decrementStockForPaidOrder($order);
    }

    public function testDecrementLigneInvalideProductIdNullLeveException(): void
    {
        $order = $this->makeOrder([
            $this->makeLine(productId: null, qty: 1),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalide');

        $this->allocator->decrementStockForPaidOrder($order);
    }

    public function testDecrementLigneInvalideQtyZeroLeveException(): void
    {
        $order = $this->makeOrder([
            $this->makeLine(productId: 1, qty: 0),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalide');

        $this->allocator->decrementStockForPaidOrder($order);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeProduct(int $id, int $stock): Product
    {
        $product = new Product();
        // On utilise la réflexion pour setter l'id (normalement géré par Doctrine)
        $ref = new \ReflectionProperty(Product::class, 'id');
        $ref->setValue($product, $id);

        $product->setStock($stock);

        return $product;
    }

    private function makeLine(?int $productId, int $qty): OrderDetails
    {
        $line = $this->createStub(OrderDetails::class);
        $line->method('getProductId')->willReturn($productId);
        $line->method('getQuantity')->willReturn($qty);

        return $line;
    }

    private function makeOrder(array $lines): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getOrderDetails')->willReturn(new ArrayCollection($lines));

        return $order;
    }
}
