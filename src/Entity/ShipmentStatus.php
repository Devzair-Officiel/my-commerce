<?php

namespace App\Entity;

use App\Repository\ShipmentStatusRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShipmentStatusRepository::class)]
class ShipmentStatus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'shipmentStatuses')]
    private ?Shipment $shipment = null;

    // Code brut du transporteur
    #[ORM\Column(length: 255)]
    private ?string $statusCode = null;

    // Libellé (en français si possible)
    #[ORM\Column(length: 255)]
    private ?string $label = null;

    // Quand l’événement a eu lieu
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $occuredAt = null;

    // Données d’origine si utile
    #[ORM\Column(nullable: true)]
    private ?array $rawData = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShipment(): ?Shipment
    {
        return $this->shipment;
    }

    public function setShipment(?Shipment $shipment): static
    {
        $this->shipment = $shipment;

        return $this;
    }

    public function getStatusCode(): ?string
    {
        return $this->statusCode;
    }

    public function setStatusCode(string $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getOccuredAt(): ?\DateTimeImmutable
    {
        return $this->occuredAt;
    }

    public function setOccuredAt(?\DateTimeImmutable $occuredAt): static
    {
        $this->occuredAt = $occuredAt;

        return $this;
    }

    public function getRawData(): ?array
    {
        return $this->rawData;
    }

    public function setRawData(?array $rawData): static
    {
        $this->rawData = $rawData;

        return $this;
    }
}
