<?php

namespace App\Entity;

use App\Trait\SeoFieldsTrait;
use App\Trait\DateTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: ProductRepository::class)]
#[Assert\Callback('validatePricing')]
class Product
{
    use DateTrait, SeoFieldsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Le titre doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 10,
        max: 255,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'La description ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'La description détaillée ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $more_description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'Les infos additionnelles ne doivent pas dépasser {{ limit }} caractères.'
    )]
    private ?string $additional_infos = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $originCountry = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le stock ne peut pas être négatif.')]
    private ?int $stock = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive(message: 'Le poids doit être strictement positif.')]
    private ?int $weightGrams = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'Le prix soldé doit être strictement positif.')]
    private ?int $solde_price = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Le prix régulier est obligatoire.')]
    #[Assert\Positive(message: 'Le prix régulier doit être strictement positif.')]
    private ?int $regular_price = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'products')]
    private Collection $categories;

    // #[ORM\Column(length: 255, nullable: true)]
    // private ?string $brand = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isAvailable = true;

    #[ORM\Column(nullable: true)]
    private ?bool $isBestSeller = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isNewArrival = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isFeatured = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isSpecialOffer = null;

    /**
     * @var Collection<int, Media>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Media::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'id' => 'ASC'])]
    private Collection $medias;

    #[ORM\ManyToMany(targetEntity: self::class)]
    #[ORM\JoinTable(name: 'product_related')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'related_product_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $relatedProducts;

    /**
     * @var Collection<int, Wishlist>
     */
    #[ORM\ManyToMany(targetEntity: Wishlist::class, mappedBy: 'products')]
    private Collection $wishlists;



    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->medias = new ArrayCollection();
        $this->wishlists = new ArrayCollection();
        $this->relatedProducts = new ArrayCollection();
    }

    // ---------------------------------------------------------------------
    // Validation métier (cohérence promo/prix)
    // ---------------------------------------------------------------------

    public function validatePricing(ExecutionContextInterface $context): void
    {
        // regular_price obligatoire (déjà couvert), mais on évite toute division par 0/valeurs incohérentes
        if ($this->regular_price === null) {
            return;
        }

        // Si prix soldé renseigné : doit être < regular_price
        if ($this->solde_price !== null) {
            if ($this->solde_price <= 0) {
                $context->buildViolation('Le prix soldé doit être strictement positif.')
                    ->atPath('solde_price')
                    ->addViolation();
                return;
            }

            if ($this->solde_price >= $this->regular_price) {
                $context->buildViolation('Le prix soldé doit être inférieur au prix régulier.')
                    ->atPath('solde_price')
                    ->addViolation();
            }
        }
    }

    // ---------------------------------------------------------------------
    // Getters / Setters (state access)
    // ---------------------------------------------------------------------

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMoreDescription(): ?string
    {
        return $this->more_description;
    }

    public function setMoreDescription(?string $more_description): static
    {
        $this->more_description = $more_description;

        return $this;
    }

    public function getAdditionalInfos(): ?string
    {
        return $this->additional_infos;
    }

    public function setAdditionalInfos(?string $additional_infos): static
    {
        $this->additional_infos = $additional_infos;

        return $this;
    }

    public function getOriginCountry(): ?string
    {
        return $this->originCountry;
    }
    public function setOriginCountry(?string $v): self
    {
        $this->originCountry = $v;
        return $this;
    }


    public function getStock(): ?int
    {
        return $this->stock;
    }

    public function setStock(?int $stock): static
    {
        $this->stock = $stock;

        return $this;
    }

    public function getWeightGrams(): ?int
    {
        return $this->weightGrams;
    }

    public function setWeightGrams(?int $weightGrams): self
    {
        $this->weightGrams = $weightGrams;
        return $this;
    }

    public function getSoldePrice(): ?int
    {
        return $this->solde_price;
    }

    public function setSoldePrice(?int $solde_price): static
    {
        $this->solde_price = $solde_price;

        return $this;
    }

    public function getRegularPrice(): ?int
    {
        return $this->regular_price;
    }

    public function setRegularPrice(int $regular_price): static
    {
        $this->regular_price = $regular_price;

        return $this;
    }

    // public function getBrand(): ?string
    // {
    //     return $this->brand;
    // }

    // public function setBrand(?string $brand): static
    // {
    //     $this->brand = $brand;

    //     return $this;
    // }

    public function isAvailable(): ?bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(?bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;

        return $this;
    }

    public function isBestSeller(): ?bool
    {
        return $this->isBestSeller;
    }

    public function setIsBestSeller(?bool $isBestSeller): static
    {
        $this->isBestSeller = $isBestSeller;

        return $this;
    }

    public function isNewArrival(): ?bool
    {
        return $this->isNewArrival;
    }

    public function setIsNewArrival(?bool $isNewArrival): static
    {
        $this->isNewArrival = $isNewArrival;

        return $this;
    }

    public function isFeatured(): ?bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(?bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;

        return $this;
    }

    public function isSpecialOffer(): ?bool
    {
        return $this->isSpecialOffer;
    }

    public function setIsSpecialOffer(?bool $isSpecialOffer): static
    {
        $this->isSpecialOffer = $isSpecialOffer;

        return $this;
    }

    // ---------------------------------------------------------------------
    // Relations helpers (collections)
    // ---------------------------------------------------------------------

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    /**
     * @return Collection<int, Media>
     */
    public function getMedias(): Collection
    {
        return $this->medias;
    }

    public function addMedia(Media $media): static
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->setProduct($this);
        }

        return $this;
    }

    public function removeMedia(Media $media): static
    {
        if ($this->medias->removeElement($media)) {
            if ($media->getProduct() === $this) {
                $media->setProduct(null);
            }
        }

        return $this;
    }

    // ---------------------------------------------------------------------
    // Domain helpers (display / pricing business rules)
    // ---------------------------------------------------------------------

    /**
     * True si le produit a un prix soldé valide (ex: soldePrice non null, > 0 et < regularPrice).
     * Permet d’éviter d’afficher une promo quand il n’y en a pas.
     */
    public function isOnSale(): bool
    {
        return $this->solde_price !== null
            && $this->solde_price > 0
            && $this->regular_price !== null
            && $this->solde_price < $this->regular_price;
    }

    /**
     * Pourcentage de remise (entier) si le produit est soldé, sinon null.
     * Exemple : 1990 -> 1490 = 25 (%), arrondi au plus proche.
     */
    public function getDiscountPercentage(): ?int
    {
        if (!$this->isOnSale()) {
            return null;
        }

        return (int) round(
            (($this->regular_price - $this->solde_price) * 100) / $this->regular_price
        );
    }

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * Liste des noms de fichiers médias (utile Twig / front).
     */
    public function getMediaFilenames(): array
    {
        $filenames = [];

        foreach ($this->getMedias() as $media) {
            $name = $media?->getFilename();
            if (is_string($name) && $name !== '') {
                $filenames[] = $name;
            }
        }

        return $filenames;
    }

    public function getCoverFilename(): ?string
    {
        // 1) on privilégie un media marqué cover
        foreach ($this->getMedias() as $media) {
            if ($media->isCover() === true) {
                $name = $media->getFilename();
                if (is_string($name) && $name !== '') {
                    return $name;
                }
            }
        }

        // 2) fallback : première image valide (ton helper existant)
        foreach ($this->getMediaFilenames() as $name) {
            return $name;
        }

        return null;
    }
    
    public function getMediaData(): array
    {
        $data = [];

        foreach ($this->getMedias() as $media) {
            $filename = $media?->getFilename();
            if (!is_string($filename) || $filename === '') {
                continue;
            }

            $alt = $media?->getAlt();
            $data[] = [
                'filename' => $filename,
                'alt' => (is_string($alt) && $alt !== '') ? $alt : null,
            ];
        }

        return $data;
    }

    

    // ---------------------------------------------------------------------
    // Misc
    // ---------------------------------------------------------------------

    public function __toString(): string
    {
        return (string) $this->title;
    }

    /** @return Collection<int, self> */
    public function getRelatedProducts(): Collection
    {
        return $this->relatedProducts;
    }

    public function addRelatedProduct(self $product): self
    {
        if ($product === $this) {
            return $this; // évite qu’un produit se référence lui-même
        }

        if (!$this->relatedProducts->contains($product)) {
            $this->relatedProducts->add($product);
        }

        return $this;
    }

    public function removeRelatedProduct(self $product): self
    {
        $this->relatedProducts->removeElement($product);

        return $this;
    }

    /**
     * @return Collection<int, Wishlist>
     */
    public function getWishlists(): Collection
    {
        return $this->wishlists;
    }

    public function addWishlist(Wishlist $wishlist): static
    {
        if (!$this->wishlists->contains($wishlist)) {
            $this->wishlists->add($wishlist);
            // Optionnel : si tu veux garder la synchro bidirectionnelle
            if (!$wishlist->getProducts()->contains($this)) {
                $wishlist->addProduct($this);
            }
        }

        return $this;
    }

    public function removeWishlist(Wishlist $wishlist): static
    {
        if ($this->wishlists->removeElement($wishlist)) {
            $wishlist->removeProduct($this);
        }

        return $this;
    }

}
