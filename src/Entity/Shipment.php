<?php

namespace App\Entity;

use App\Repository\ShipmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipmentRepository::class)]
class Shipment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // Référence à la commande
    #[ORM\ManyToOne(inversedBy: 'shipments')]
    private ?Order $customerOrder = null;

    // Transporteur
    #[ORM\ManyToOne(inversedBy: 'shipments')]
    private ?Carrier $carrier = null;

    // Numéro de suivi
    #[ORM\Column(length: 255)]
    private ?string $trackingNumber = null;

    // URL publique de suivi
    #[ORM\Column(length: 255)]
    private ?string $trackingUrl = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pickupPointId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pickupPointName = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pickupPointAddress = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $pickupPointPostalCode = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pickupPointCity = null;

    /** URL du bon de transport PDF (généré par l'API transporteur) */
    #[ORM\Column(length: 512, nullable: true)]
    private ?string $labelUrl = null;

    /** Poids réel du colis en grammes */
    #[ORM\Column(nullable: true)]
    private ?int $weightGrams = null;

    /** Code statut transporteur (ex: Colissimo "LIVCFM", MR "80") */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $statusCode = null;

    /** Dernière synchronisation statut avec l'API transporteur */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $syncedAt = null;

    /** Message d'erreur si la création de l'étiquette a échoué */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    // Quand tu as expédié
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $shippedAt = null;


    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    // Timestamp création
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, ShipmentStatus>
     */
    #[ORM\OneToMany(targetEntity: ShipmentStatus::class, mappedBy: 'shipment')]
    private Collection $shipmentStatuses;

    public function __construct()
    {
        $this->shipmentStatuses = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerOrder(): ?Order
    {
        return $this->customerOrder;
    }

    public function setCustomerOrder(?Order $customerOrder): static
    {
        $this->customerOrder = $customerOrder;

        return $this;
    }

    public function getCarrier(): ?Carrier
    {
        return $this->carrier;
    }

    public function setCarrier(?Carrier $carrier): static
    {
        $this->carrier = $carrier;

        return $this;
    }

    public function getTrackingNumber(): ?string
    {
        return $this->trackingNumber;
    }

    public function setTrackingNumber(string $trackingNumber): static
    {
        $this->trackingNumber = $trackingNumber;

        return $this;
    }

    public function getTrackingUrl(): ?string
    {
        return $this->trackingUrl;
    }

    public function setTrackingUrl(string $trackingUrl): static
    {
        $this->trackingUrl = $trackingUrl;

        return $this;
    }

    public function getPickupPointId(): ?string
    {
        return $this->pickupPointId;
    }

    public function setPickupPointId(?string $pickupPointId): static
    {
        $this->pickupPointId = $pickupPointId;

        return $this;
    }

    public function getPickupPointName(): ?string
    {
        return $this->pickupPointName;
    }

    public function setPickupPointName(?string $pickupPointName): static
    {
        $this->pickupPointName = $pickupPointName;

        return $this;
    }

    public function getPickupPointAddress(): ?string
    {
        return $this->pickupPointAddress;
    }

    public function setPickupPointAddress(?string $pickupPointAddress): static
    {
        $this->pickupPointAddress = $pickupPointAddress;

        return $this;
    }

    public function getPickupPointPostalCode(): ?string
    {
        return $this->pickupPointPostalCode;
    }

    public function setPickupPointPostalCode(?string $pickupPointPostalCode): static
    {
        $this->pickupPointPostalCode = $pickupPointPostalCode;

        return $this;
    }

    public function getPickupPointCity(): ?string
    {
        return $this->pickupPointCity;
    }

    public function setPickupPointCity(?string $pickupPointCity): static
    {
        $this->pickupPointCity = $pickupPointCity;

        return $this;
    }

    public function getLabelUrl(): ?string { return $this->labelUrl; }
    public function setLabelUrl(?string $labelUrl): static { $this->labelUrl = $labelUrl; return $this; }

    public function getWeightGrams(): ?int { return $this->weightGrams; }
    public function setWeightGrams(?int $weightGrams): static { $this->weightGrams = $weightGrams; return $this; }

    public function getStatusCode(): ?string { return $this->statusCode; }
    public function setStatusCode(?string $statusCode): static { $this->statusCode = $statusCode; return $this; }

    public function getSyncedAt(): ?\DateTimeImmutable { return $this->syncedAt; }
    public function setSyncedAt(?\DateTimeImmutable $syncedAt): static { $this->syncedAt = $syncedAt; return $this; }

    public function getErrorMessage(): ?string { return $this->errorMessage; }
    public function setErrorMessage(?string $errorMessage): static { $this->errorMessage = $errorMessage; return $this; }

    public function getShippedAt(): ?\DateTimeImmutable
    {
        return $this->shippedAt;
    }

    public function setShippedAt(\DateTimeImmutable $shippedAt): static
    {
        $this->shippedAt = $shippedAt;

        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, ShipmentStatus>
     */
    public function getShipmentStatuses(): Collection
    {
        return $this->shipmentStatuses;
    }

    public function addShipmentStatus(ShipmentStatus $shipmentStatus): static
    {
        if (!$this->shipmentStatuses->contains($shipmentStatus)) {
            $this->shipmentStatuses->add($shipmentStatus);
            $shipmentStatus->setShipment($this);
        }

        return $this;
    }

    public function removeShipmentStatus(ShipmentStatus $shipmentStatus): static
    {
        if ($this->shipmentStatuses->removeElement($shipmentStatus)) {
            // set the owning side to null (unless already changed)
            if ($shipmentStatus->getShipment() === $this) {
                $shipmentStatus->setShipment(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('#%d - %s (%s)', $this->id, $this->trackingNumber ?? 'Sans suivi', $this->carrier?->getName() ?? 'Sans transporteur');
    }
}
