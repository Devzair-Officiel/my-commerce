<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message Messenger déclenché pour envoyer l'e-mail de notification de remboursement au client.
 */
final readonly class SendRefundEmailMessage
{
    public function __construct(
        public int $orderId,
    ) {}
}
