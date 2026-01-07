<?php

namespace App\Entity;

use App\Trait\DateTrait;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\OrderDetailsRepository;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Auditable;

#[ORM\Entity(repositoryClass: OrderDetailsRepository::class)]
#[ORM\HasLifecycleCallbacks]
class OrderDetails
{
    use DateTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $product_name = null;

    #[ORM\Column(length: 255)]
    private ?string $product_description = null;

    #[ORM\Column(nullable: true)]
    private ?int $product_solde_price = null;

    #[ORM\Column]
    private ?int $product_regular_price = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column(nullable: true)]
    private ?int $taxe = null;

    #[ORM\Column]
    private ?int $subtotal = null;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'OrderDetails')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Order $myOrder = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProductName(): ?string
    {
        return $this->product_name;
    }

    public function setProductName(string $product_name): static
    {
        $this->product_name = $product_name;

        return $this;
    }

    public function getProductDescription(): ?string
    {
        return $this->product_description;
    }

    public function setProductDescription(string $product_description): static
    {
        $this->product_description = $product_description;

        return $this;
    }

    public function getProductSoldePrice(): ?int
    {
        return $this->product_solde_price;
    }

    public function setProductSoldePrice(?int $product_solde_price): static
    {
        $this->product_solde_price = $product_solde_price;

        return $this;
    }

    public function getProductRegularPrice(): ?int
    {
        return $this->product_regular_price;
    }

    public function setProductRegularPrice(int $product_regular_price): static
    {
        $this->product_regular_price = $product_regular_price;

        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getTaxe(): ?int
    {
        return $this->taxe;
    }

    public function setTaxe(?int $taxe): static
    {
        $this->taxe = $taxe;

        return $this;
    }

    public function getSubtotal(): ?int
    {
        return $this->subtotal;
    }

    public function setSubtotal(int $subtotal): static
    {
        $this->subtotal = $subtotal;

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
