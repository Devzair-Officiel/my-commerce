<?php

namespace App\Entity;

use App\Repository\StripeWebhookEventRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StripeWebhookEventRepository::class)]
#[ORM\Table(name: 'stripe_webhook_event')]
#[ORM\UniqueConstraint(name: 'uniq_stripe_event_id', columns: ['stripe_event_id'])]
class StripeWebhookEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'stripe_event_id', length: 255)]
    private string $stripeEventId;

    #[ORM\Column(length: 100)]
    private string $type;

    #[ORM\Column]
    private \DateTimeImmutable $receivedAt;

    public function __construct(string $stripeEventId, string $type)
    {
        $this->stripeEventId = $stripeEventId;
        $this->type = $type;
        $this->receivedAt = new \DateTimeImmutable();
    }

    public function getStripeEventId(): string
    {
        return $this->stripeEventId;
    }
}