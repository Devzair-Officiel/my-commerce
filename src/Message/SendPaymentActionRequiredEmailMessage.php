<?php

declare(strict_types=1);

namespace App\Message;

final readonly class SendPaymentActionRequiredEmailMessage
{
    public function __construct(
        public int    $orderId,
        public string $paymentIntentId,
    ) {}
}
