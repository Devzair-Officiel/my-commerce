<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message Messenger déclenché pour envoyer l'e-mail de confirmation de livraison au client.
 */
final readonly class SendDeliveredEmailMessage
{
    public function __construct(
        public int $shipmentId,
    ) {}
}
