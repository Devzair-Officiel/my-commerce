<?php

namespace App\Entity;

use App\Entity\Media;
use App\Trait\DateTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\PaymentMethodRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use DH\Auditor\Provider\Doctrine\Auditing\Annotation\Auditable;

#[ORM\Entity(repositoryClass: PaymentMethodRepository::class)]
class PaymentMethod
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $more_description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $test_public_api_key = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $test_private_api_key = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $testBaseUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $prodBaseUrl = null;

    #[ORM\OneToOne(mappedBy: 'paymentMethod', cascade: ['persist', 'remove'])]
    private ?Media $mediaPayment = null;

    #[ORM\OneToMany(mappedBy: 'paymentMethod', targetEntity: Order::class)]
    private Collection $orders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

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

    public function getTestPublicApiKey(): ?string
    {
        return $this->test_public_api_key;
    }

    public function setTestPublicApiKey(?string $test_public_api_key): static
    {
        $this->test_public_api_key = $test_public_api_key;

        return $this;
    }

    public function getTestPrivateApiKey(): ?string
    {
        return $this->test_private_api_key;
    }

    public function setTestPrivateApiKey(?string $test_private_api_key): static
    {
        $this->test_private_api_key = $test_private_api_key;

        return $this;
    }

    public function getTestBaseUrl(): ?string
    {
        return $this->testBaseUrl;
    }

    public function setTestBaseUrl(?string $testBaseUrl): static
    {
        $this->testBaseUrl = $testBaseUrl;

        return $this;
    }

    public function getProdBaseUrl(): ?string
    {
        return $this->prodBaseUrl;
    }

    public function setProdBaseUrl(?string $prodBaseUrl): static
    {
        $this->prodBaseUrl = $prodBaseUrl;

        return $this;
    }

    public function getMediaPayment(): ?Media
    {
        return $this->mediaPayment;
    }

    public function setMediaPayment(?Media $mediaPayment): static
    {
        // unset the owning side of the relation if necessary
        if ($mediaPayment === null && $this->mediaPayment !== null) {
            $this->mediaPayment->setPaymentMethod(null);
        }

        // set the owning side of the relation if necessary
        if ($mediaPayment !== null && $mediaPayment->getPaymentMethod() !== $this) {
            $mediaPayment->setPaymentMethod($this);
        }

        $this->mediaPayment = $mediaPayment;

        return $this;
    }

    /** @return Collection<int, Order> */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function __toString()
    {
        return $this->name;
    }
}
