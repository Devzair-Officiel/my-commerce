<?php

namespace App\Doctrine\Listener;

use App\Entity\Page;
use App\Entity\Setting;
use App\Entity\Category;
use Doctrine\ORM\Events;
use App\Storefront\StorefrontGlobalsProvider;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
final class StorefrontGlobalsCacheInvalidationListener
{
    public function __construct(
        private readonly StorefrontGlobalsProvider $globalsProvider
    ) {}

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->invalidateIfSupported($args->getObject());
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->invalidateIfSupported($args->getObject());
    }

    public function postRemove(LifecycleEventArgs $args): void
    {
        $this->invalidateIfSupported($args->getObject());
    }

    private function invalidateIfSupported(object $entity): void
    {
        if ($entity instanceof Setting) {
            $this->globalsProvider->invalidateSetting();
            return;
        }

        if ($entity instanceof Page) {
            $this->globalsProvider->invalidatePages();
            return;
        }

        if ($entity instanceof Category) {
            $this->globalsProvider->invalidateCategories();
            return;
        }
    }
}
