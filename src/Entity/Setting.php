<?php

namespace App\Entity;

use App\Entity\Media;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\SettingRepository;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $website_name = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $currency = null;

    #[ORM\Column(nullable: true)]
    private ?int $taxe_rate = null;

    #[ORM\Column(length: 255)]
    private ?string $street = null;

    #[ORM\Column(length: 255)]
    private ?string $city = null;

    #[ORM\Column(length: 255)]
    private ?string $code_postal = null;

    #[ORM\Column(length: 255)]
    private ?string $state = null;

    #[ORM\Column(length: 255)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailNoReply = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebookLink = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $instaLink = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $youtubeLink = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $copyright = null;

    #[ORM\Column(nullable: true)]
    private ?int $freeShippingThresholdCents = null;

    /**
     * Informations du micro-entrepreneur pour les factures.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ownerName = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $siret = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $legalMentions = null;

    #[ORM\OneToOne(mappedBy: 'setting', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private ?Media $logoMedia = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWebsiteName(): ?string
    {
        return $this->website_name;
    }

    public function setWebsiteName(string $website_name): static
    {
        $this->website_name = $website_name;

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

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getTaxeRate(): ?int
    {
        return $this->taxe_rate;
    }

    public function setTaxeRate(?int $taxe_rate): static
    {
        $this->taxe_rate = $taxe_rate;

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(string $street): static
    {
        $this->street = $street;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->code_postal;
    }

    public function setCodePostal(string $code_postal): static
    {
        $this->code_postal = $code_postal;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): static
    {
        $this->state = $state;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getEmailNoReply(): ?string
    {
        return $this->emailNoReply;
    }

    public function setEmailNoReply(?string $emailNoReply): static
    {
        $this->emailNoReply = $emailNoReply;

        return $this;
    }

    public function getFacebookLink(): ?string
    {
        return $this->facebookLink;
    }

    public function setFacebookLink(?string $facebookLink): static
    {
        $this->facebookLink = $facebookLink;

        return $this;
    }

    public function getInstaLink(): ?string
    {
        return $this->instaLink;
    }

    public function setInstaLink(?string $instaLink): static
    {
        $this->instaLink = $instaLink;

        return $this;
    }

    public function getYoutubeLink(): ?string
    {
        return $this->youtubeLink;
    }

    public function setYoutubeLink(?string $youtubeLink): static
    {
        $this->youtubeLink = $youtubeLink;

        return $this;
    }

    public function getCopyright(): ?string
    {
        return $this->copyright;
    }

    public function setCopyright(?string $copyright): static
    {
        $this->copyright = $copyright;

        return $this;
    }

    public function getFreeShippingThresholdCents(): ?int
    {
        return $this->freeShippingThresholdCents;
    }

    public function setFreeShippingThresholdCents(?int $value): static
    {
        $this->freeShippingThresholdCents = $value;
        return $this;
    }

    public function getLogoMedia(): ?Media
    {
        return $this->logoMedia;
    }

    public function setLogoMedia(?Media $media): static
    {
        $this->logoMedia = $media;

        if ($media !== null && $media->getSetting() !== $this) {
            $media->setSetting($this);
        }

        return $this;
    }

    public function getOwnerName(): ?string
    {
        return $this->ownerName;
    }

    public function setOwnerName(?string $ownerName): static
    {
        $this->ownerName = $ownerName;
        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): static
    {
        $this->siret = $siret;
        return $this;
    }

    public function getLegalMentions(): ?string
    {
        return $this->legalMentions;
    }

    public function setLegalMentions(?string $legalMentions): static
    {
        $this->legalMentions = $legalMentions;
        return $this;
    }

    public function getLogoAlt(): ?string
    {
        return $this->logoMedia?->getAlt();
    }

    public function setLogoAlt(?string $alt): static
    {
        if ($this->logoMedia === null) {
            $this->setLogoMedia(new Media());
        }

        $this->logoMedia->setAlt($alt);

        return $this;
    }

}
