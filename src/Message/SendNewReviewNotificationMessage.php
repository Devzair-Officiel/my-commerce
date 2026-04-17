<?php

declare(strict_types=1);

namespace App\Message;

final readonly class SendNewReviewNotificationMessage
{
    public function __construct(public int $reviewId) {}
}
