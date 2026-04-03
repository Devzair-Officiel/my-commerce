<?php

namespace App\Entity;

use App\Trait\DateTrait;
use App\Enum\PaymentStatus;
use Doctrine\DBAL\Types\Types;
use App\Enum\FulfillmentStatus;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

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
    private PaymentStatus $paymentStatus = PaymentStatus::Pending;

    #[ORM\Column(type: Types::STRING, length: 20, enumType: FulfillmentStatus::class)]
    #[Assert\NotNull]
    private FulfillmentStatus $fulfillmentStatus = FulfillmentStatus::Draft;

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

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cartClearedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $confirmationEmailSentAt = null;

    #[ORM\OneToMany(
        mappedBy: 'myOrder',
        targetEntity: OrderDetails::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    
    #[Assert\Valid] // valide aussi les lignes
    private Collection $orderDetails;

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

    // METHODE UTILITAIRE //

    public function getTotalWeightKgFormatted(): string
    {
        return number_format($this->totalWeightGrams / 1000, 3, ',', ' ') . ' kg';
    }
}
