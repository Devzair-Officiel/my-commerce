<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use App\Trait\DateTrait;
use App\Trait\SeoFieldsTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;


/**
 * Entité représentant une catégorie de produits, avec image, description SEO et option d'affichage en méga-menu.
 */
#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[Assert\Callback('validateMegaRequiresProducts')]
class Category
{
    use DateTrait, SeoFieldsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    // petit texte au-dessus des produits
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $intro = null;

    // bloc SEO plus complet
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isMega = null;

    private ?UploadedFile $imageFile = null;

    /**
     * @var Collection<int, Product>
     */
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'categories')]
    private Collection $products;

    #[ORM\OneToOne(mappedBy: 'category', cascade: ['persist', 'remove'])]
    private ?Media $media = null;

    public function __construct()
    {
        $this->products = new ArrayCollection();
    }

    // ---------------------------------------------------------------------
    // Validation métier
    // ---------------------------------------------------------------------
    public function validateMegaRequiresProducts(ExecutionContextInterface $context): void
    {
        if ($this->isMega !== true) {
            return;
        }

        if ($this->products->count() < 1) {
            $context->buildViolation('Si "Mega menu" est activé, la catégorie doit avoir au moins un produit associé.')
                ->atPath('isMega')
                ->addViolation();
        }
    }
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getIntro(): ?string
    {
        return $this->intro;
    }

    public function setintro(?string $intro): static
    {
        $this->intro = $intro;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function isMega(): ?bool
    {
        return $this->isMega;
    }

    public function setIsMega(?bool $isMega): static
    {
        $this->isMega = $isMega;

        return $this;
    }

    public function getImageFile(): ?UploadedFile
    {
        return $this->imageFile;
    }

    public function setImageFile(?UploadedFile $imageFile): self
    {
        $this->imageFile = $imageFile;

        return $this;
    }

    /**
     * @return Collection<int, Product>
     */
    public function getProducts(): Collection
    {
        return $this->products;
    }

    public function addProduct(Product $product): static
    {
        if (!$this->products->contains($product)) {
            $this->products->add($product);
            $product->addCategory($this);
        }

        return $this;
    }

    public function removeProduct(Product $product): static
    {
        if ($this->products->removeElement($product)) {
            $product->removeCategory($this);
        }

        return $this;
    }

    public function __toString()
    {
        return $this->title;
    }

    public function getMedia(): ?Media
    {
        return $this->media;
    }

    public function setMedia(?Media $media): static
    {
        // unset the owning side of the relation if necessary
        if ($media === null && $this->media !== null) {
            $this->media->setCategory(null);
        }

        // set the owning side of the relation if necessary
        if ($media !== null && $media->getCategory() !== $this) {
            $media->setCategory($this);
        }

        $this->media = $media;

        return $this;
    }
}
