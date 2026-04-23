<?php

namespace App\Entity;

use App\Repository\SlidersRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entité représentant un slide du carrousel de la page d'accueil (titre, image, bouton d'appel à l'action).
 */
#[ORM\Entity(repositoryClass: SlidersRepository::class)]
class Sliders
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $button_text = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $mediaSliderMobile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $button_link = null;

    #[ORM\OneToOne(mappedBy: 'sliders', cascade: ['persist', 'remove'])]
    private ?Media $mediaSlider = null;

    public function getId(): ?int { return $this->id; }

    public function getTitle(): ?string { return $this->title; }
    public function setTitle(?string $title): static { $this->title = $title; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getButtonText(): ?string { return $this->button_text; }
    public function setButtonText(?string $button_text): static { $this->button_text = $button_text; return $this; }

    public function getButtonLink(): ?string { return $this->button_link; }
    public function setButtonLink(?string $button_link): static { $this->button_link = $button_link; return $this; }

    public function getMediaSliderMobile(): ?string { return $this->mediaSliderMobile; }
    public function setMediaSliderMobile(?string $filename): static { $this->mediaSliderMobile = $filename; return $this; }

    public function getMediaSlider(): ?Media { return $this->mediaSlider; }

    public function setMediaSlider(?Media $mediaSlider): static
    {
        if ($mediaSlider === null && $this->mediaSlider !== null) {
            $this->mediaSlider->setSliders(null);
        }
        if ($mediaSlider !== null && $mediaSlider->getSliders() !== $this) {
            $mediaSlider->setSliders($this);
        }
        $this->mediaSlider = $mediaSlider;
        return $this;
    }
}
