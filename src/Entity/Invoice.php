<?php

namespace App\Entity;

use App\Enum\InvoiceStatus;
use App\Repository\InvoiceRepository;
use App\Trait\DateTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Invoice
{
    use DateTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Numéro séquentiel unique : FAC-2026-0001
     */
    #[ORM\Column(length: 30, unique: true)]
    private string $invoiceNumber = '';

    #[ORM\Column(type: Types::STRING, length: 20, enumType: InvoiceStatus::class)]
    private InvoiceStatus $status = InvoiceStatus::Draft;

    #[ORM\OneToOne(inversedBy: 'invoice')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $customerOrder = null;

    /**
     * Snapshot des informations du vendeur (micro-entrepreneur) au moment de l'émission.
     * Stocké en JSON pour historisation.
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $sellerSnapshot = null;

    /**
     * Mentions légales et notes personnalisables (ex: conditions de paiement).
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * Date d'émission officielle de la facture (null tant que status = draft).
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $issuedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoiceNumber(): string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(string $invoiceNumber): static
    {
        $this->invoiceNumber = $invoiceNumber;
        return $this;
    }

    public function getStatus(): InvoiceStatus
    {
        return $this->status;
    }

    public function setStatus(InvoiceStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isIssued(): bool
    {
        return $this->status === InvoiceStatus::Issued;
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
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

    public function getSellerSnapshot(): ?array
    {
        return $this->sellerSnapshot;
    }

    public function setSellerSnapshot(?array $sellerSnapshot): static
    {
        $this->sellerSnapshot = $sellerSnapshot;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getIssuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?\DateTimeImmutable $issuedAt): static
    {
        $this->issuedAt = $issuedAt;
        return $this;
    }

    /**
     * Émet la facture : verrouille le statut et horodate.
     */
    public function issue(): static
    {
        $this->status   = InvoiceStatus::Issued;
        $this->issuedAt = new \DateTimeImmutable();
        return $this;
    }
}
