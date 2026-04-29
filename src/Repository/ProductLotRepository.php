<?php

namespace App\Repository;

use App\Entity\Product;
use App\Entity\ProductLot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProductLot>
 */
class ProductLotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductLot::class);
    }

    public function findCurrentForProduct(Product $product): ?ProductLot
    {
        return $this->createQueryBuilder('l')
            ->where('l.product = :product')
            ->andWhere('l.isCurrent = true')
            ->setParameter('product', $product)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return ProductLot[] */
    public function findHistoryForProduct(Product $product): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.product = :product')
            ->setParameter('product', $product)
            ->orderBy('l.receivedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
