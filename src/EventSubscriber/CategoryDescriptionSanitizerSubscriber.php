<?php

namespace App\EventSubscriber;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Sanitise automatiquement la description HTML des catégories avant sauvegarde.
 *
 * Rôle :
 * - intercepter les opérations Doctrine prePersist et preUpdate ;
 * - nettoyer le HTML saisi en back-office avec le sanitizer Symfony ;
 * - garantir qu'aucun HTML non autorisé ou dangereux ne soit enregistré.
 *
 * Objectif :
 * sécuriser le contenu riche provenant de l'administration tout en conservant
 * un sous-ensemble de balises autorisées pour l'affichage SEO.
 *
 * Périmètre :
 * ce subscriber s'applique uniquement à l'entité Category.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class CategoryDescriptionSanitizerSubscriber
{
    public function __construct(
        private readonly HtmlSanitizerInterface $appCategoryDescriptionSanitizer,
    ) {}

    /**
     * Nettoie la description lors de la création d'une catégorie.
     */
    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Category) {
            return;
        }

        $this->sanitizeCategoryDescription($entity);
    }

    /**
     * Nettoie la description lors de la mise à jour d'une catégorie
     * puis recalcule le changeset Doctrine.
     */
    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Category) {
            return;
        }

        $this->sanitizeCategoryDescription($entity);

        $em = $args->getObjectManager();
        $metadata = $em->getClassMetadata(Category::class);
        $em->getUnitOfWork()->recomputeSingleEntityChangeSet($metadata, $entity);
    }

    /**
     * Applique le sanitizer Symfony sur la description de catégorie.
     *
     * Si la description est vide, elle est normalisée à null.
     */
    private function sanitizeCategoryDescription(Category $category): void
    {
        $description = $category->getDescription();

        if ($description === null || trim($description) === '') {
            $category->setDescription(null);

            return;
        }

        $sanitized = $this->appCategoryDescriptionSanitizer->sanitize($description);

        $category->setDescription($sanitized);
    }
}
