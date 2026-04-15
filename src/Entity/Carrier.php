<?php

namespace App\Entity;

use App\Enum\CarrierType;
use App\Repository\CarrierRepository;
use App\Trait\DateTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CarrierRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Carrier
{
    use DateTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Type de transporteur (manuel, colissimo) */
    #[ORM\Column(type: 'string', length: 50, enumType: CarrierType::class, options: ['default' => 'manual'])]
    private CarrierType $type = CarrierType::Manual;

    /** Prix fixe en centimes */
    #[ORM\Column]
    private ?int $price = null;

    /** Délai indicatif affiché au client (ex: "2-3 jours ouvrés") */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $deliveryDelay = null;

    /** Indique si ce transporteur propose des points relais */
    #[ORM\Column(options: ['default' => false])]
    private bool $hasPickupPoints = false;

    #[ORM\OneToMany(mappedBy: 'carrier', targetEntity: Order::class)]
    private Collection $orders;

    /** @var Collection<int, Shipment> */
    #[ORM\OneToMany(targetEntity: Shipment::class, mappedBy: 'carrier')]
    private Collection $shipments;

    public function __construct()
    {
        $this->orders    = new ArrayCollection();
        $this->shipments = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getType(): CarrierType { return $this->type; }
    public function setType(CarrierType $type): static { $this->type = $type; return $this; }

    public function getPrice(): ?int { return $this->price; }
    public function setPrice(int $price): static { $this->price = $price; return $this; }

    public function getDeliveryDelay(): ?string { return $this->deliveryDelay; }
    public function setDeliveryDelay(?string $deliveryDelay): static { $this->deliveryDelay = $deliveryDelay; return $this; }

    public function hasPickupPoints(): bool { return $this->hasPickupPoints; }
    public function setHasPickupPoints(bool $hasPickupPoints): static { $this->hasPickupPoints = $hasPickupPoints; return $this; }

    public function __toString(): string { return $this->name ?? ''; }

    /** @return Collection<int, Shipment> */
    public function getShipments(): Collection { return $this->shipments; }

    public function addShipment(Shipment $shipment): static
    {
        if (!$this->shipments->contains($shipment)) {
            $this->shipments->add($shipment);
            $shipment->setCarrier($this);
        }
        return $this;
    }

    public function removeShipment(Shipment $shipment): static
    {
        if ($this->shipments->removeElement($shipment) && $shipment->getCarrier() === $this) {
            $shipment->setCarrier(null);
        }
        return $this;
    }
}
