<?php

declare(strict_types=1);

namespace App\Message;

final readonly class SendRefundEmailMessage
{
    public function __construct(
        public int $orderId,
    ) {}
}
