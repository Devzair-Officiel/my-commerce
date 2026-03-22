<?php

namespace App\Trait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait SeoFieldsTrait
{
    #[ORM\Column(length: 70)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    private ?string $seoTitle = null;

    #[ORM\Column(length: 170)]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    private ?string $seoDescription = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $seoNoindex = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoOgImage = null; // URL ou chemin public

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $seoCanonicalOverride = null;

    public function getSeoTitle(): ?string
    {
        return $this->seoTitle;
    }
    public function setSeoTitle(?string $v): self
    {
        $this->seoTitle = $v;
        return $this;
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }
    public function setSeoDescription(?string $v): self
    {
        $this->seoDescription = $v;
        return $this;
    }

    public function isSeoNoindex(): bool
    {
        return $this->seoNoindex;
    }
    public function setSeoNoindex(bool $v): self
    {
        $this->seoNoindex = $v;
        return $this;
    }

    public function getSeoOgImage(): ?string
    {
        return $this->seoOgImage;
    }
    public function setSeoOgImage(?string $v): self
    {
        $this->seoOgImage = $v;
        return $this;
    }

    public function getSeoCanonicalOverride(): ?string
    {
        return $this->seoCanonicalOverride;
    }
    public function setSeoCanonicalOverride(?string $v): self
    {
        $this->seoCanonicalOverride = $v;
        return $this;
    }
}
