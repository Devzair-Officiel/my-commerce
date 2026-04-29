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
 * Tests unitaires pour les méthodes de réservation de stock (StockAllocator).
 *
 * Scénarios couverts :
 *  - Réservation normale : stock décrémenté + order marqué réservé
 *  - Réservation avec stock insuffisant → exception, stock inchangé
 *  - Réservation sur produit à stock non géré (null) → ignoré
 *  - Re-réservation (panier modifié) : ancienne réservation libérée avant nouvelle
 *  - Libération de réservation : stock restauré + flag effacé
 *  - Libération sans réservation préalable → aucun effet
 *  - decrementStockForPaidOrder flux normal
 *  - decrementStockForPaidOrder stock insuffisant → exception
 *  - restoreStockForCancelledOrder flux normal
 *  - restoreStockForCancelledOrder sans décrément préalable → no-op
 */
class StockAllocatorReservationTest extends TestCase
{
    private StockAllocator $allocator;
    private EntityManagerInterface&Stub $em;

    protected function setUp(): void
    {
        $this->em = $this->createStub(EntityManagerInterface::class);
        $this->allocator = new StockAllocator($this->em);
    }

    public function testReservationNormale(): void
    {
        $product = $this->makeProduct(id: 1, stock: 10);
        $this->em->method('find')->willReturn($product);

        $order = $this->makeRealOrder([
            $this->makeLine(productId: 1, qty: 3),
        ]);

        $this->allocator->reserveStockForDraftOrder($order);

        $this->assertSame(7, $product->getStock());
        $this->assertTrue($order->isStockReserved());
    }

    public function testReservationStockInsuffisantLeveException(): void
    {
        $product = $this->makeProduct(id: 1, stock: 2);
        $this->em->method('find')->willReturn($product);

        $order = $this->makeRealOrder([
            $this->makeLine(productId: 1, qty: 5),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stock insuffisant');

        $this->allocator->reserveStockForDraftOrder($order);

        // Le stock ne doit pas avoir changé
        $this->assertSame(2, $product->getStock());
    }

    public function testReservationProduitStockNonGereIgnore(): void
    {
        $product = $this->makeProduct(id: 1, stock: null);
        $this->em->method('find')->willReturn($product);

        $order = $this->makeRealOrder([
            $this->makeLine(productId: 1, qty: 99),
        ]);

        $this->allocator->reserveStockForDraftOrder($order);

        $this->assertNull($product->getStock());
        $this->assertTrue($order->isStockReserved());
    }

    public function testReReservationLibereLancienneAvantNouvelle(): void
    {
        $product = $this->makeProduct(id: 1, stock: 3);
        $this->em->method('find')->willReturn($product);

        $order = $this->makeRealOrder([
            $this->makeLine(productId: 1, qty: 2),
        ]);

        // Première réservation avec les anciennes lignes (qty=2)
        $this->allocator->reserveStockForDraftOrder($order);
        $this->assertSame(1, $product->getStock()); // 3 - 2 = 1

        // L'appelant (CheckoutController) libère AVANT de changer les lignes
        $this->allocator->releaseStockReservation($order);
        $this->assertSame(3, $product->getStock()); // 1 + 2 = 3

        // Nouvelles lignes (qty=3) puis re-réservation
        $this->setOrderLines($order, [$this->makeLine(productId: 1, qty: 3)]);
        $this->allocator->reserveStockForDraftOrder($order);
        $this->assertSame(0, $product->getStock()); // 3 - 3 = 0
        $this->assertTrue($order->isStockReserved());
    }

    public function testLiberationRestaureLeStock(): void
    {
        $product = $this->makeProduct(id: 1, stock: 5);
        $this->em->method('find')->willReturn($product);

        $order = $this->makeRealOrder([
            $this->makeLine(productId: 1, qty: 3),
        ]);

        $this->allocator->reserveStockForDraftOrder($order);
        $this->assertSame(2, $product->getStock()); // 5-3=2

        $this->allocator->releaseStockReservation($order);
        $this->assertSame(5, $product->getStock()); // 2+3=5
        $this->assertFalse($order->isStockReserved());
    }

    public function testLiberationSansReservationPrecedenteNeFaitRien(): void
    {
        $product = $this->makeProduct(id: 1, stock: 5);
        $this->em->method('find')->willReturn($product);

        $order = $this->makeRealOrder([
            $this->makeLine(productId: 1, qty: 3),
        ]);

        // Pas de réservation préalable → aucun effet
        $this->allocator->releaseStockReservation($order);

        $this->assertSame(5, $product->getStock());
        $this->assertFalse($order->isStockReserved());
    }

    public function testDecrementStockFluxNormal(): void
    {
        $product = $this->makeProduct(id: 1, stock: 10);
        $this->em->method('find')->willReturn($product);

        $order = $this->makeRealOrder([
            $this->makeLine(productId: 1, qty: 4),
        ]);

        $this->allocator->decrementStockForPaidOrder($order);

        $this->assertSame(6, $product->getStock());
    }

    public function testDecrementStockInsuffisantLeveException(): void
    {
        $product = $this->makeProduct(id: 1, stock: 2);
        $this->em->method('find')->willReturn($product);

        $order = $this->makeRealOrder([
            $this->makeLine(productId: 1, qty: 5),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stock insuffisant');

        $this->allocator->decrementStockForPaidOrder($order);
    }

    public function testRestoreStockFluxNormal(): void
    {
        $product = $this->makeProduct(id: 1, stock: 0);
        $this->em->method('find')->willReturn($product);

        $order = $this->makeRealOrder([
            $this->makeLine(productId: 1, qty: 3),
        ]);

        // Simule un stock déjà décrémenté
        $prop = new \ReflectionProperty(Order::class, 'stockDecrementedAt');
        $prop->setValue($order, new \DateTimeImmutable());

        $this->allocator->restoreStockForCancelledOrder($order);

        $this->assertSame(3, $product->getStock());
    }

    public function testRestoreStockSansDecrementPrecedentNeFaitRien(): void
    {
        $product = $this->makeProduct(id: 1, stock: 5);
        $this->em->method('find')->willReturn($product);

        $order = $this->makeRealOrder([
            $this->makeLine(productId: 1, qty: 3),
        ]);

        // stockDecrementedAt non défini → guard dans restoreStockForCancelledOrder
        $this->allocator->restoreStockForCancelledOrder($order);

        $this->assertSame(5, $product->getStock());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeProduct(int $id, ?int $stock): Product
    {
        $product = new Product();
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

    private function makeRealOrder(array $lines): Order
    {
        $order = new Order();
        $this->setOrderLines($order, $lines);
        return $order;
    }

    private function setOrderLines(Order $order, array $lines): void
    {
        $prop = new \ReflectionProperty(Order::class, 'orderDetails');
        $prop->setValue($order, new ArrayCollection($lines));
    }
}
