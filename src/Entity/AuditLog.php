<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AuditAction;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(columns: ['entity_class', 'entity_id'], name: 'idx_audit_entity')]
#[ORM\Index(columns: ['performed_at'], name: 'idx_audit_at')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Nom court de l'entité : Order, Product, User… */
    #[ORM\Column(length: 50)]
    private string $entityClass = '';

    #[ORM\Column]
    private int $entityId = 0;

    #[ORM\Column(length: 10, enumType: AuditAction::class)]
    private AuditAction $action = AuditAction::Update;

    /** Diff JSON : {changes: {field: {before, after}}} ou {after: {...}} ou {before: {...}} */
    #[ORM\Column(type: Types::JSON)]
    private array $changes = [];

    /** Email de l'utilisateur connecté, ou 'system' */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $performedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $performedAt;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    public function __construct()
    {
        $this->performedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getEntityClass(): string { return $this->entityClass; }
    public function setEntityClass(string $v): static { $this->entityClass = $v; return $this; }

    public function getEntityId(): int { return $this->entityId; }
    public function setEntityId(int $v): static { $this->entityId = $v; return $this; }

    public function getAction(): AuditAction { return $this->action; }
    public function setAction(AuditAction $v): static { $this->action = $v; return $this; }

    public function getChanges(): array { return $this->changes; }
    public function setChanges(array $v): static { $this->changes = $v; return $this; }

    /** Getter virtuel pour EasyAdmin — retourne le JSON brut en string. */
    public function getChangesRaw(): string
    {
        return \json_encode($this->changes, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    public function getPerformedBy(): ?string { return $this->performedBy; }
    public function setPerformedBy(?string $v): static { $this->performedBy = $v; return $this; }

    public function getPerformedAt(): \DateTimeImmutable { return $this->performedAt; }
    public function setPerformedAt(\DateTimeImmutable $v): static { $this->performedAt = $v; return $this; }

    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $v): static { $this->ipAddress = $v; return $this; }
}
