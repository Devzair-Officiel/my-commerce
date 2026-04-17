<?php

namespace App\Entity;

use App\Repository\OrderDetailsRepository;
use App\Trait\DateTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité représentant une ligne de commande : produit, quantité, prix unitaire et montant total.
 */
#[ORM\Entity(repositoryClass: OrderDetailsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class OrderDetails
{
    use DateTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive]
    private ?int $productId = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $productName = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 4000)]
    private ?string $productDescription = null;

    #[ORM\Column(length: 2048, nullable: true)]
    #[Assert\Length(max: 2048)]
    private ?string $productImageUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $productImageAlt = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $unitPriceCents = 0;

    #[ORM\Column]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $taxAmountCents = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $lineTotalCents = 0;

    #[ORM\ManyToOne(inversedBy: 'orderDetails')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Order $myOrder = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function setProductId(?int $productId): static
    {
        $this->productId = $productId;

        return $this;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function setProductName(string $productName): static
    {
        $this->productName = $productName;

        return $this;
    }

    public function getProductDescription(): ?string
    {
        return $this->productDescription;
    }

    public function setProductDescription(?string $productDescription): static
    {
        $this->productDescription = $productDescription;

        return $this;
    }

    public function getProductImageUrl(): ?string
    {
        return $this->productImageUrl;
    }

    public function setProductImageUrl(?string $productImageUrl): static
    {
        $this->productImageUrl = $productImageUrl;

        return $this;
    }

    public function getProductImageAlt(): ?string
    {
        return $this->productImageAlt;
    }

    public function setProductImageAlt(?string $productImageAlt): static
    {
        $this->productImageAlt = $productImageAlt;

        return $this;
    }

    public function getUnitPriceCents(): int
    {
        return $this->unitPriceCents;
    }

    public function setUnitPriceCents(int $unitPriceCents): static
    {
        $this->unitPriceCents = $unitPriceCents;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getTaxAmountCents(): ?int
    {
        return $this->taxAmountCents;
    }

    public function setTaxAmountCents(?int $taxAmountCents): static
    {
        $this->taxAmountCents = $taxAmountCents;

        return $this;
    }

    public function getLineTotalCents(): int
    {
        return $this->lineTotalCents;
    }

    public function setLineTotalCents(int $lineTotalCents): static
    {
        $this->lineTotalCents = $lineTotalCents;

        return $this;
    }

    public function getMyOrder(): ?Order
    {
        return $this->myOrder;
    }

    public function setMyOrder(?Order $myOrder): static
    {
        $this->myOrder = $myOrder;

        return $this;
    }
}
