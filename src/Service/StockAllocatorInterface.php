<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Order;

interface StockAllocatorInterface
{
    public function reserveStockForDraftOrder(Order $order): void;
    public function releaseStockReservation(Order $order): void;
    public function decrementStockForPaidOrder(Order $order): void;
    public function restoreStockForCancelledOrder(Order $order): void;
}
