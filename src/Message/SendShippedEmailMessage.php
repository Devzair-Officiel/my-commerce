<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message Messenger déclenché pour envoyer l'e-mail d'expédition au client lors du passage en statut « Expédiée ».
 */
final readonly class SendShippedEmailMessage
{
    public function __construct(
        public int $orderId,
    ) {}
}
