<?php

declare(strict_types=1);

namespace App\Message;

final readonly class NotifyAdminFraudWarningMessage
{
    public function __construct(
        public int    $orderId,
        public string $fraudWarningId,
        public string $fraudType,
        public bool   $actionable,
    ) {}
}
