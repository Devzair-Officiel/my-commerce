<?php

declare(strict_types=1);

namespace App\Message;

final readonly class SendBackInStockAlertMessage
{
    public function __construct(
        public int $productId,
    ) {}
}
