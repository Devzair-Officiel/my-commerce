<?php

namespace App\Entity;

use App\Entity\Invoice;
use App\Trait\DateTrait;
use App\Enum\PaymentStatus;
use Doctrine\DBAL\Types\Types;
use App\Enum\FulfillmentStatus;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité principale d'une commande client : articles, adresse, transporteur, statuts de paiement et d'expédition.
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
#[ORM\HasLifecycleCallbacks]
class Order
{
    use DateTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Propriétaire de la commande (sécurité/ownership).
     */
    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?User $user = null;

    /**
     * Adresse figée (snapshot) pour historiser.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 4000)]
    private ?string $shippingAddress = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 4000)]
    private ?string $billingAddress = null;

    /**
     * Point relais choisi (JSON snapshot) — non null uniquement si le transporteur est Mondial Relay.
     * Exemple : {"id":"004100","name":"Tabac Presse","address":"10 rue du Général De Gaulle","city":"Paris","postalCode":"75001"}
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $pickupPointSnapshot = null;

    /**
     * Montants en CENTIMES.
     */
    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private int $itemsTotalHtCents = 0;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $taxAmountCents = null;

    #[ORM\Column]
    #[Assert\NotNull]
    #[Assert\PositiveOrZero]
    private int $orderTotalTtcCents = 0;

    /**
     * Devise (ISO 4217). Tu peux forcer "EUR" si tu veux.
     */
    #[ORM\Column(length: 3)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(type: Types::STRING, length: 20, enumType: PaymentStatus::class)]
    #[Assert\NotNull]
    private PaymentStatus $paymentStatus = PaymentStatus::Attente;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: FulfillmentStatus::class)]
    #[Assert\NotNull]
    private FulfillmentStatus $fulfillmentStatus = FulfillmentStatus::Brouillon;

    /**
     * Carrier : relation + snapshot.
     */
    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Carrier $carrier = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $carrierNameSnapshot = '';

    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $carrierPriceSnapshotCents = 0;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero]
    private ?int $totalWeightGrams = 0;

    /**
     * Paiement : relation + snapshot.
     */
    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PaymentMethod $paymentMethod = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $paymentMethodNameSnapshot = null;

    #[ORM\Column(length: 50, unique: true, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $orderReference = null;

    /**
     * Référence provider (ex: Stripe payment_intent id / PayPal order id).
     */
    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $paymentReference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $paymentFailureReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /**
     * Horodatage de la réservation de stock au checkout. Null = pas encore réservé.
     * La réservation est libérée si le paiement échoue ou est annulé.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $stockReservedAt = null;

    /**
     * Horodatage du décrément de stock. Sert de guard contre les doubles décréments.
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $stockDecrementedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cartClearedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmationEmailSentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $refundEmailSentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $shippingEmailSentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $reviewRequestEmailSentAt = null;

    #[ORM\OneToMany(
        mappedBy: 'myOrder',
        targetEntity: OrderDetails::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    
    #[Assert\Valid] // valide aussi les lignes
    private Collection $orderDetails;

    #[ORM\OneToOne(mappedBy: 'customerOrder', targetEntity: Invoice::class, cascade: ['persist', 'remove'])]
    private ?Invoice $invoice = null;

    /**
     * @var Collection<int, Shipment>
     */
    #[ORM\OneToMany(targetEntity: Shipment::class, mappedBy: 'customerOrder', cascade: ['persist'])]
    private Collection $shipments;

    public function __construct()
    {
        $this->orderDetails = new ArrayCollection();
        $this->shipments = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getShippingAddress(): ?string
    {
        return $this->shippingAddress;
    }
    public function setShippingAddress(?string $shippingAddress): static
    {
        $this->shippingAddress = $shippingAddress !== null ? trim($shippingAddress) : null;
        return $this;
    }

    public function getBillingAddress(): ?string
    {
        return $this->billingAddress;
    }
    public function setBillingAddress(?string $billingAddress): static
    {
        $this->billingAddress = $billingAddress !== null ? trim($billingAddress) : null;
        return $this;
    }

    public function getPickupPointSnapshot(): ?string { return $this->pickupPointSnapshot; }
    public function setPickupPointSnapshot(?string $json): static { $this->pickupPointSnapshot = $json; return $this; }

    /** @return array{id:string,name:string,address:string,city:string,postalCode:string}|null */
    public function getPickupPoint(): ?array
    {
        if ($this->pickupPointSnapshot === null) {
            return null;
        }
        $data = json_decode($this->pickupPointSnapshot, true);
        return is_array($data) ? $data : null;
    }

    public function getItemsTotalHtCents(): int
    {
        return $this->itemsTotalHtCents;
    }
    public function setItemsTotalHtCents(int $cents): static
    {
        $this->itemsTotalHtCents = $cents;
        return $this;
    }

    public function getTaxAmountCents(): ?int
    {
        return $this->taxAmountCents;
    }
    public function setTaxAmountCents(?int $cents): static
    {
        $this->taxAmountCents = $cents;
        return $this;
    }

    public function getOrderTotalTtcCents(): int
    {
        return $this->orderTotalTtcCents;
    }
    public function setOrderTotalTtcCents(int $cents): static
    {
        $this->orderTotalTtcCents = $cents;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }
    public function setCurrency(string $currency): static
    {
        $this->currency = strtoupper($currency);
        return $this;
    }

    public function getPaymentStatus(): PaymentStatus
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(PaymentStatus $status): static
    {
        $this->paymentStatus = $status;
        return $this;
    }

    public function getFulfillmentStatus(): FulfillmentStatus
    {
        return $this->fulfillmentStatus;
    }

    public function setFulfillmentStatus(FulfillmentStatus $status): static
    {
        $this->fulfillmentStatus = $status;
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

    public function getCarrierNameSnapshot(): string
    {
        return $this->carrierNameSnapshot;
    }
    public function setCarrierNameSnapshot(string $name): static
    {
        $this->carrierNameSnapshot = trim($name);
        return $this;
    }

    public function getCarrierPriceSnapshotCents(): int
    {
        return $this->carrierPriceSnapshotCents;
    }
    public function setCarrierPriceSnapshotCents(int $cents): static
    {
        $this->carrierPriceSnapshotCents = $cents;
        return $this;
    }

    public function getTotalWeightGrams(): int
    {
        return $this->totalWeightGrams;
    }

    public function setTotalWeightGrams(int $totalWeightGrams): self
    {
        $this->totalWeightGrams = max(0, $totalWeightGrams);

        return $this;
    }

    public function getPaymentMethod(): ?PaymentMethod
    {
        return $this->paymentMethod;
    }
    public function setPaymentMethod(?PaymentMethod $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getPaymentMethodNameSnapshot(): ?string
    {
        return $this->paymentMethodNameSnapshot;
    }
    public function setPaymentMethodNameSnapshot(?string $name): static
    {
        $this->paymentMethodNameSnapshot = $name !== null ? trim($name) : null;
        return $this;
    }

    public function getOrderReference(): ?string
    {
        return $this->orderReference;
    }

    public function setOrderReference(?string $orderReference): static
    {
        $this->orderReference = $orderReference;

        return $this;
    }

    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }
    public function setPaymentReference(?string $ref): static
    {
        $this->paymentReference = $ref !== null ? trim($ref) : null;
        return $this;
    }

    public function setPaymentFailureReason(?string $reason): static
    {
        $this->paymentFailureReason = $reason;
        return $this;
    }

    public function getStockReservedAt(): ?\DateTimeImmutable
    {
        return $this->stockReservedAt;
    }

    public function markStockReserved(): static
    {
        $this->stockReservedAt = new \DateTimeImmutable();
        return $this;
    }

    public function clearStockReservation(): static
    {
        $this->stockReservedAt = null;
        return $this;
    }

    public function isStockReserved(): bool
    {
        return $this->stockReservedAt !== null;
    }

    public function getStockDecrementedAt(): ?\DateTimeImmutable
    {
        return $this->stockDecrementedAt;
    }

    public function markStockDecremented(): static
    {
        $this->stockDecrementedAt = new \DateTimeImmutable();
        return $this;
    }

    public function isStockDecremented(): bool
    {
        return $this->stockDecrementedAt !== null;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }
    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getCartClearedAt(): ?\DateTimeImmutable
    {
        return $this->cartClearedAt;
    }

    public function markCartCleared(): void
    {
        $this->cartClearedAt = new \DateTimeImmutable();
    }

    public function getConfirmationEmailSentAt(): ?\DateTimeImmutable
    {
        return $this->confirmationEmailSentAt;
    }

    public function setConfirmationEmailSentAt(?\DateTimeImmutable $confirmationEmailSentAt): static
    {
        $this->confirmationEmailSentAt = $confirmationEmailSentAt;

        return $this;
    }

    public function markConfirmationEmailSent(): static
    {
        $this->confirmationEmailSentAt = new \DateTimeImmutable();

        return $this;
    }

    public function isConfirmationEmailSent(): bool
    {
        return null !== $this->confirmationEmailSentAt;
    }

    public function isRefundEmailSent(): bool
    {
        return null !== $this->refundEmailSentAt;
    }

    public function markRefundEmailSent(): static
    {
        $this->refundEmailSentAt = new \DateTimeImmutable();

        return $this;
    }

    public function isShippingEmailSent(): bool
    {
        return null !== $this->shippingEmailSentAt;
    }

    public function markShippingEmailSent(): static
    {
        $this->shippingEmailSentAt = new \DateTimeImmutable();

        return $this;
    }

    public function hasReviewRequestEmailBeenSent(): bool
    {
        return null !== $this->reviewRequestEmailSentAt;
    }

    public function markReviewRequestEmailSent(): static
    {
        $this->reviewRequestEmailSentAt = new \DateTimeImmutable();

        return $this;
    }

    /** @return Collection<int, OrderDetails> */
    public function getOrderDetails(): Collection
    {
        return $this->orderDetails;
    }

    public function addOrderDetail(OrderDetails $orderDetail): static
    {
        if (!$this->orderDetails->contains($orderDetail)) {
            $this->orderDetails->add($orderDetail);
            $orderDetail->setMyOrder($this);
        }
        return $this;
    }

    public function removeOrderDetail(OrderDetails $orderDetail): static
    {
        if ($this->orderDetails->removeElement($orderDetail)) {
            if ($orderDetail->getMyOrder() === $this) {
                $orderDetail->setMyOrder(null);
            }
        }
        return $this;
    }

    public function generateOrderReferenceIfMissing(): static
    {
        if (null !== $this->orderReference) {
            return $this;
        }

        $date = new \DateTimeImmutable();
        $random = strtoupper(bin2hex(random_bytes(3)));

        $this->orderReference = sprintf('CMD-%s-%s', $date->format('Ymd'), $random);

        return $this;
    }

    /**
     * @return Collection<int, Shipment>
     */
    public function getShipments(): Collection
    {
        return $this->shipments;
    }

    public function addShipments(Shipment $shipments): static
    {
        if (!$this->shipments->contains($shipments)) {
            $this->shipments->add($shipments);
            $shipments->setCustomerOrder($this);
        }

        return $this;
    }

    public function removeShipments(Shipment $shipments): static
    {
        if ($this->shipments->removeElement($shipments)) {
            // set the owning side to null (unless already changed)
            if ($shipments->getCustomerOrder() === $this) {
                $shipments->setCustomerOrder(null);
            }
        }

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        $this->invoice = $invoice;
        return $this;
    }

    // METHODE UTILITAIRE //

    public function getTotalWeightKgFormatted(): string
    {
        return number_format($this->totalWeightGrams / 1000, 3, ',', ' ') . ' kg';
    }

    public function __toString(): string
    {
        return $this->orderReference ?? '#' . $this->id;
    }
}
