<?php

namespace App\Entity;

use App\Repository\WholesaleTierRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WholesaleTierRepository::class)]
#[ORM\Table(name: 'wholesale_tier')]
class WholesaleTier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'wholesaleTiers')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column]
    #[Assert\Positive(message: 'La quantité minimale doit être positive.')]
    private int $minQtyKg = 1;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $label = null;

    #[ORM\Column]
    #[Assert\Positive(message: 'Le prix doit être positif.')]
    private int $unitPriceCents = 0;

    public function getId(): ?int { return $this->id; }

    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): static { $this->product = $product; return $this; }

    public function getMinQtyKg(): int { return $this->minQtyKg; }
    public function setMinQtyKg(int $minQtyKg): static { $this->minQtyKg = $minQtyKg; return $this; }

    public function getLabel(): ?string { return $this->label; }
    public function setLabel(?string $label): static { $this->label = $label; return $this; }

    public function getUnitPriceCents(): int { return $this->unitPriceCents; }
    public function setUnitPriceCents(int $unitPriceCents): static { $this->unitPriceCents = $unitPriceCents; return $this; }

    public function __toString(): string
    {
        return sprintf('À partir de %d kg — %s €', $this->minQtyKg, number_format($this->unitPriceCents / 100, 2, ',', ' '));
    }
}
