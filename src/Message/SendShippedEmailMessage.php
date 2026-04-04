<?php

declare(strict_types=1);

namespace App\Message;

final readonly class SendShippedEmailMessage
{
    public function __construct(
        public int $orderId,
    ) {}
}
