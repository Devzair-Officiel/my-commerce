<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findByCategoriesForLayout(array $categoryIds): array
    {
        return $this->createQueryBuilder('p')
            ->select('
            p.id AS id,
            p.title AS title,
            p.slug AS slug,
            p.regular_price AS price,
            c.id AS category_id
        ')
            ->innerJoin('p.categories', 'c')
            ->andWhere('c.id IN (:categoryIds)')
            ->setParameter('categoryIds', $categoryIds)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function getByCategories($category)
    {

        return $this->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->where('c.id = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getResult();
    }
}
