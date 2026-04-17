<?php

namespace App\Entity;

use App\Trait\DateTrait;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\AddressRepository;

/**
 * Entité représentant une adresse de livraison ou de facturation associée à un compte client.
 */
#[ORM\Entity(repositoryClass: AddressRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Address
{
    use DateTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $client_name = null;

    #[ORM\Column(length: 255)]
    private ?string $street = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $code_postal = null;

    #[ORM\Column(length: 255)]
    private ?string $city = null;

    #[ORM\Column(length: 255)]
    private ?string $state = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $more_details = null;

    #[ORM\ManyToOne(inversedBy: 'addresses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address_type = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

        return $this;
    }

    public function getClientName(): ?string
    {
        return $this->client_name;
    }

    public function setClientName(string $client_name): static
    {
        $this->client_name = trim($client_name);

        return $this;
    }

    public function getStreet(): ?string
    {
        return $this->street;
    }

    public function setStreet(string $street): static
    {
        $this->street = trim($street);

        return $this;
    }

    public function getCodePostal(): ?string
    {
        return $this->code_postal;
    }

    public function setCodePostal(?string $code_postal): static
    {
        $this->code_postal = null !== $code_postal ? trim($code_postal) : null;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = trim($city);

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): static
    {
        $this->state = trim($state);

        return $this;
    }

    public function getMoreDetails(): ?string
    {
        return $this->more_details;
    }

    public function setMoreDetails(?string $more_details): static
    {
        $this->more_details = null !== $more_details ? trim($more_details) : null;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getAddressType(): ?string
    {
        return $this->address_type;
    }

    public function setAddressType(?string $address_type): static
    {
        $this->address_type = null !== $address_type ? trim($address_type) : null;

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('Adresse #%d', $this->id ?? 0);
    }

    public function toMultilineSnapshot(): string
    {
        $lines = array_filter([
            $this->getClientName(),
            $this->getName() ? '(' . trim($this->getName()) . ')' : null,
            $this->getStreet(),
            trim(sprintf(
                '%s %s',
                (string) $this->getCodePostal(),
                (string) $this->getCity()
            )),
            $this->getState(),
            $this->getMoreDetails(),
        ], static fn($value): bool => is_string($value) && trim($value) !== '');

        return implode("\n", array_map(
            static fn(string $value): string => trim($value),
            $lines
        ));
    }

    public function toSnapshotString(): string
    {
        return str_replace("\n", ' ', $this->toMultilineSnapshot());
    }
}
