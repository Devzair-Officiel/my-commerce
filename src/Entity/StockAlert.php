<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\StockAlertRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockAlertRepository::class)]
#[ORM\Table(name: 'stock_alert')]
#[ORM\UniqueConstraint(name: 'uniq_stock_alert_email_product', columns: ['email', 'product_id'])]
class StockAlert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Product $product;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $notifiedAt = null;

    public function __construct(string $email, Product $product)
    {
        $this->email     = $email;
        $this->product   = $product;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function getProduct(): Product { return $this->product; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getNotifiedAt(): ?\DateTimeImmutable { return $this->notifiedAt; }

    public function markNotified(): void
    {
        $this->notifiedAt = new \DateTimeImmutable();
    }
}
