<?php

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Catégories mega menu (scalaires uniquement)
     */
    public function findMegaCategoriesForLayout(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.id AS id, c.title AS title, c.slug AS slug, c.description AS description')
            ->andWhere('c.isMega = true')
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

}
