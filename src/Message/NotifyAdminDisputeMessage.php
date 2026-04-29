<?php

declare(strict_types=1);

namespace App\Message;

final readonly class NotifyAdminDisputeMessage
{
    public function __construct(
        public int    $orderId,
        public string $disputeId,
        public string $reason,
        public int    $amountCents,
    ) {}
}
