<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\MediaRepository;
use Symfony\Component\HttpFoundation\File\UploadedFile;

#[ORM\Entity(repositoryClass: MediaRepository::class)]
class Media
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $alt = null;

    #[ORM\Column(nullable: true)]
    private ?int $position = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isCover = null;

    private ?UploadedFile $upload = null;

    #[ORM\ManyToOne(inversedBy: 'medias')]
    private ?Product $product = null;

    #[ORM\OneToOne(inversedBy: 'media', cascade: ['persist', 'remove'])]
    private ?Category $category = null;

    #[ORM\OneToOne(inversedBy: 'mediaPayment', cascade: ['persist', 'remove'])]
    private ?PaymentMethod $paymentMethod = null;

    #[ORM\OneToOne(inversedBy: 'logoMedia')]
    private ?Setting $setting = null;

    #[ORM\OneToOne(inversedBy: 'mediaSlider', cascade: ['persist', 'remove'])]
    private ?Sliders $sliders = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getAlt(): ?string
    {
        return $this->alt;
    }

    public function setAlt(?string $alt): static
    {
        $this->alt = $alt;

        return $this;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function isCover(): ?bool
    {
        return $this->isCover;
    }

    public function setIsCover(?bool $isCover): static
    {
        $this->isCover = $isCover;

        return $this;
    }

    public function setUpload(?UploadedFile $file): void
    {
        $this->upload = $file;
    }
    public function getUpload(): ?UploadedFile
    {
        return $this->upload;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?PaymentMethod $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getSetting(): ?Setting
    {
        return $this->setting;
    }

    public function setSetting(?Setting $setting): static
    {
        $this->setting = $setting;

        return $this;
    }

    public function __toString()
    {
        return $this->filename;
    }

    public function getSliders(): ?Sliders
    {
        return $this->sliders;
    }

    public function setSliders(?Sliders $sliders): static
    {
        $this->sliders = $sliders;

        return $this;
    }
}
