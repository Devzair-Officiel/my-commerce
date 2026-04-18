<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\StockAlert;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StockAlert>
 */
class StockAlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StockAlert::class);
    }

    /** Alertes non encore notifiées pour un produit donné. */
    public function findPendingByProduct(Product $product): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.product = :product')
            ->andWhere('a.notifiedAt IS NULL')
            ->setParameter('product', $product)
            ->getQuery()
            ->getResult();
    }

    public function findByEmailAndProduct(string $email, Product $product): ?StockAlert
    {
        return $this->findOneBy(['email' => $email, 'product' => $product]);
    }

    public function countByProduct(Product $product): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.product = :product')
            ->andWhere('a.notifiedAt IS NULL')
            ->setParameter('product', $product)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
