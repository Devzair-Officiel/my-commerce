<?php

namespace App\Entity;

use App\Repository\HoneyTastingRepository;
use App\Trait\DateTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fiche de dégustation de miel remplie par un dégustateur.
 * Les évaluations sensorielles (cases cochées et textes libres) sont stockées en JSON
 * pour absorber la variabilité des descripteurs sans multiplier les colonnes.
 */
#[ORM\Entity(repositoryClass: HoneyTastingRepository::class)]
#[ORM\Table(name: 'honey_tasting')]
#[ORM\HasLifecycleCallbacks]
class HoneyTasting
{
    use DateTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Merci d\'indiquer votre nom.')]
    #[Assert\Length(min: 2, max: 120)]
    private ?string $tasterName = null;

    /**
     * Structure : { "<honey-slug>__<section-slug>__<field-slug>": string | string[] }
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $data = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTasterName(): ?string
    {
        return $this->tasterName;
    }

    public function setTasterName(string $tasterName): static
    {
        $this->tasterName = $tasterName;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }
}
