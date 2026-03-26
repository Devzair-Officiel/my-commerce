<?php

namespace App\Trait;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait SeoFieldsTrait
{
    #[ORM\Column(length: 70, nullable: true)]
    #[Assert\Length(
        max: 70,
        maxMessage: 'Le meta title ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $seoTitle = null;

    #[ORM\Column(length: 170, nullable: true)]
    #[Assert\Length(
        max: 170,
        maxMessage: 'La meta description ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $seoDescription = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $seoNoindex = false;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L’image Open Graph ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $seoOgImage = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L’URL canonique ne doit pas dépasser {{ limit }} caractères.'
    )]
    private ?string $seoCanonicalOverride = null;

    public function getSeoTitle(): ?string
    {
        return $this->seoTitle;
    }

    public function setSeoTitle(?string $v): self
    {
        $this->seoTitle = $v !== null ? trim($v) : null;
        return $this;
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function setSeoDescription(?string $v): self
    {
        $this->seoDescription = $v !== null ? trim($v) : null;
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
        $this->seoOgImage = $v !== null ? trim($v) : null;
        return $this;
    }

    public function getSeoCanonicalOverride(): ?string
    {
        return $this->seoCanonicalOverride;
    }

    public function setSeoCanonicalOverride(?string $v): self
    {
        $this->seoCanonicalOverride = $v !== null ? trim($v) : null;
        return $this;
    }
}
