<?php

namespace App\EventListener;

use App\Entity\Category;
use App\Entity\Page;
use App\Entity\Setting;
use App\Storefront\StorefrontGlobalsProvider;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Invalide sélectivement le cache du storefront lorsque des entités clés
 * (Setting, Page, Category) sont modifiées ou supprimées en back-office.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class StorefrontGlobalsCacheInvalidationListener
{
    private bool $invalidateSetting = false;
    private bool $invalidatePages = false;
    private bool $invalidateCategories = false;

    public function __construct(
        private readonly StorefrontGlobalsProvider $globalsProvider
    ) {}

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->markIfSupported($entity);
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->markIfSupported($entity);
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->markIfSupported($entity);
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        // Invalider une seule fois, après le flush complet (fiable en admin)
        if ($this->invalidateSetting) {
            $this->globalsProvider->invalidateSetting();
        }
        if ($this->invalidatePages) {
            $this->globalsProvider->invalidatePages();
        }
        if ($this->invalidateCategories) {
            $this->globalsProvider->invalidateCategories();
        }

        // reset flags
        $this->invalidateSetting = false;
        $this->invalidatePages = false;
        $this->invalidateCategories = false;
    }

    private function markIfSupported(object $entity): void
    {
        if ($entity instanceof Setting) {
            $this->invalidateSetting = true;
            return;
        }
        if ($entity instanceof Page) {
            $this->invalidatePages = true;
            return;
        }
        if ($entity instanceof Category) {
            $this->invalidateCategories = true;
            return;
        }
    }
}
