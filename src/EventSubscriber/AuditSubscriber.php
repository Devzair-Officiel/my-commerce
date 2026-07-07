<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Address;
use App\Entity\AuditLog;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use App\Enum\AuditAction;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postFlush)]
final class AuditSubscriber
{
    /** Entités surveillées → label court affiché dans l'UI */
    private const AUDITED = [
        Order::class   => 'Order',
        Product::class => 'Product',
        User::class    => 'User',
        Review::class  => 'Review',
        Address::class => 'Address',
    ];

    /** Champs exclus de l'audit (sensibles ou techniques) */
    private const EXCLUDED_FIELDS = [
        'password', 'googleId', 'pendingEmailToken',
        // Panier sauvegardé : modifié à chaque changement de panier,
        // sans valeur d'audit — pollue la table et le diff des updates User.
        'savedCart',
    ];

    /** Logs UPDATE/DELETE en attente (collectés dans onFlush) */
    private array $pending = [];

    /** Snapshots CREATE en attente — l'ID réel arrive dans postPersist */
    private array $pendingCreates = [];

    public function __construct(
        private readonly Security     $security,
        private readonly RequestStack $requestStack,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Collecte pendant le flush
    // ─────────────────────────────────────────────────────────────────────────

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em  = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        // Les CREATE sont traités dans postPersist (ID réel disponible)
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if (!$this->isAudited($entity)) {
                continue;
            }
            $this->pendingCreates[spl_object_id($entity)] = $this->snapshot($entity, $em);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$this->isAudited($entity)) {
                continue;
            }
            $rawChangeset = $uow->getEntityChangeSet($entity);
            $changes      = $this->normalizeChangeset($rawChangeset);
            if (empty($changes)) {
                continue;
            }
            $this->pending[] = $this->buildRow(
                $entity,
                AuditAction::Update,
                ['changes' => $changes],
            );
        }

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if (!$this->isAudited($entity)) {
                continue;
            }
            $this->pending[] = $this->buildRow(
                $entity,
                AuditAction::Delete,
                ['before' => $this->snapshot($entity, $em)],
            );
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // postPersist : l'ID réel est maintenant connu
    // ─────────────────────────────────────────────────────────────────────────

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        $oid    = spl_object_id($entity);

        if (!isset($this->pendingCreates[$oid])) {
            return;
        }

        $snapshot = $this->pendingCreates[$oid];
        unset($this->pendingCreates[$oid]);

        $this->pending[] = $this->buildRow(
            $entity,
            AuditAction::Create,
            ['after' => $snapshot],
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Écriture DBAL après le flush (évite tout risque de boucle ORM)
    // ─────────────────────────────────────────────────────────────────────────

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (empty($this->pending)) {
            return;
        }

        $logs        = $this->pending;
        $this->pending = [];
        $conn        = $args->getObjectManager()->getConnection();

        foreach ($logs as $row) {
            $conn->insert('audit_log', $row);
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function isAudited(object $entity): bool
    {
        foreach (self::AUDITED as $class => $_) {
            if ($entity instanceof $class) {
                return true;
            }
        }
        return false;
    }

    private function entityLabel(object $entity): string
    {
        foreach (self::AUDITED as $class => $label) {
            if ($entity instanceof $class) {
                return $label;
            }
        }
        return (new \ReflectionClass($entity))->getShortName();
    }

    /**
     * Construit le tableau de données prêt pour DBAL->insert().
     */
    private function buildRow(object $entity, AuditAction $action, array $changes): array
    {
        return [
            'entity_class' => $this->entityLabel($entity),
            'entity_id'    => (int) ($entity->getId() ?? 0),
            'action'       => $action->value,
            'changes'      => \json_encode($changes, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES),
            'performed_by' => $this->currentUserEmail(),
            'performed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'ip_address'   => $this->currentIp(),
        ];
    }

    /**
     * Photographie tous les champs scalaires d'une entité.
     */
    private function snapshot(object $entity, EntityManagerInterface $em): array
    {
        $meta = $em->getClassMetadata($entity::class);
        $data = [];

        foreach ($meta->fieldMappings as $fieldName => $mapping) {
            if (\in_array($fieldName, self::EXCLUDED_FIELDS, true)) {
                continue;
            }
            $value        = $meta->getFieldValue($entity, $fieldName);
            $data[$fieldName] = $this->normalizeValue($value);
        }

        return $data;
    }

    /**
     * Transforme le changeset Doctrine en tableau lisible {field: {before, after}}.
     */
    private function normalizeChangeset(array $changeset): array
    {
        $result = [];
        foreach ($changeset as $field => [$before, $after]) {
            if (\in_array($field, self::EXCLUDED_FIELDS, true)) {
                continue;
            }
            $result[$field] = [
                'before' => $this->normalizeValue($before),
                'after'  => $this->normalizeValue($after),
            ];
        }
        return $result;
    }

    /**
     * Convertit une valeur PHP en scalaire JSON-sérialisable.
     */
    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null || \is_string($value) || \is_int($value) || \is_float($value) || \is_bool($value)) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if (\is_object($value) && \method_exists($value, 'getId')) {
            return $value->getId();
        }
        if (\is_array($value)) {
            return \array_map($this->normalizeValue(...), $value);
        }
        return (string) $value;
    }

    private function currentUserEmail(): string
    {
        $user = $this->security->getUser();
        return $user?->getUserIdentifier() ?? 'system';
    }

    private function currentIp(): ?string
    {
        return $this->requestStack->getCurrentRequest()?->getClientIp();
    }
}
