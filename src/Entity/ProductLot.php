<?php

namespace App\Entity;

use App\Repository\ProductLotRepository;
use App\Trait\DateTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductLotRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProductLot
{
    use DateTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'lots')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    /** Numéro de lot (ex: L-YE-JU-20260628) */
    #[ORM\Column(length: 50)]
    private ?string $lotNumber = null;

    /** Date de réception de l'arrivage */
    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $receivedAt = null;

    /** Date limite de consommation / fin de validité */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    /** Lot actuellement en vente */
    #[ORM\Column(options: ['default' => false])]
    private bool $isCurrent = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function getId(): ?int { return $this->id; }

    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): static { $this->product = $product; return $this; }

    public function getLotNumber(): ?string { return $this->lotNumber; }
    public function setLotNumber(string $lotNumber): static { $this->lotNumber = $lotNumber; return $this; }

    public function getReceivedAt(): ?\DateTimeImmutable { return $this->receivedAt; }
    public function setReceivedAt(\DateTimeImmutable $receivedAt): static { $this->receivedAt = $receivedAt; return $this; }

    public function getExpiresAt(): ?\DateTimeImmutable { return $this->expiresAt; }
    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static { $this->expiresAt = $expiresAt; return $this; }

    public function isCurrent(): bool { return $this->isCurrent; }
    public function setIsCurrent(bool $isCurrent): static { $this->isCurrent = $isCurrent; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    public function __toString(): string { return $this->lotNumber ?? ''; }
}
