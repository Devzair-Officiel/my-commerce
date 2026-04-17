<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message Messenger déclenché pour envoyer l'e-mail de confirmation de commande avec la facture en pièce jointe.
 */
final readonly class SendOrderConfirmationEmailMessage
{
    public function __construct(
        public int $orderId
    ) {}
}
