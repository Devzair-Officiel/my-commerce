<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\WishlistRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WishlistRepository::class)]
class Wishlist
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** @var Collection<int, Product> */
    #[ORM\ManyToMany(targetEntity: Product::class, inversedBy: 'wishlists')]
    #[ORM\JoinTable(name: 'wishlist_product')]
    private Collection $products;

    #[ORM\OneToOne(inversedBy: 'wishlist')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $customer;

    public function __construct(User $customer)
    {
        $this->customer = $customer;
        $this->products = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /** @return Collection<int, Product> */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): self
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
        }
        return $this;
    }

    public function removeProduct(Product $product): self
    {
        $this->products->removeElement($product);
        return $this;
    }

    public function getCustomer(): User
    {
        return $this->customer;
    }

    // Optionnel : si tu veux garder un setCustomer, fais-le NON NULLABLE
    public function setCustomer(User $customer): self
    {
        $this->customer = $customer;
        return $this;
    }

    public function hasProduct(Product $product): bool
    {
        return $this->products->contains($product);
    }
}
