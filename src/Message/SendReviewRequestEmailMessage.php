<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Dispatché 24 h après la livraison pour inviter le client à laisser un avis.
 */
final readonly class SendReviewRequestEmailMessage
{
    public function __construct(
        public int $orderId,
    ) {}
}
