<?php

namespace App\EventListener;

use App\Entity\Media;
use App\Service\MediaFileManager;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;

/**
 * Écoute les événements Doctrine sur l'entité Media.
 *
 * - postRemove : supprime physiquement le fichier du disque
 *   lorsque l'entité Media est supprimée de la base de données
 *   (suppression directe ou via cascade/orphanRemoval).
 *
 * - preUpdate : supprime l'ancien fichier du disque lorsqu'un
 *   nouveau fichier remplace l'ancien (changement de filename).
 *
 * Objectif : éviter les fichiers orphelins sur le disque.
 */

#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class MediaFileCleanupListener
{
    public function __construct(private MediaFileManager $files) {}

    public function postRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Media) {
            return;
        }

        $this->files->removeFile($entity);
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Media) {
            return;
        }

        if (!$args->hasChangedField('filename')) {
            return;
        }

        $oldFilename = $args->getOldValue('filename');
        if (!is_string($oldFilename) || $oldFilename === '') {
            return;
        }

        // On supprime l'ancien fichier (avant remplacement)
        $tmp = new Media();
        $tmp->setFilename($oldFilename);
        $tmp->setProduct($entity->getProduct());
        $tmp->setCategory($entity->getCategory());
        $tmp->setSliders($entity->getSliders());
        $tmp->setSetting($entity->getSetting());

        $this->files->removeFile($tmp);
    }
}
