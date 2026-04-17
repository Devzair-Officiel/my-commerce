<?php

namespace App\Entity;

use App\Repository\PageRepository;
use App\Trait\SeoFieldsTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité représentant une page statique du site (mentions légales, CGV, politique de confidentialité, etc.).
 */
#[ORM\Entity(repositoryClass: PageRepository::class)]
class Page
{
    use SeoFieldsTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isHead = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isFoot = null;

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

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function isHead(): ?bool
    {
        return $this->isHead;
    }

    public function setIsHead(?bool $isHead): static
    {
        $this->isHead = $isHead;

        return $this;
    }

    public function isFoot(): ?bool
    {
        return $this->isFoot;
    }

    public function setIsFoot(?bool $isFoot): static
    {
        $this->isFoot = $isFoot;

        return $this;
    }
}
