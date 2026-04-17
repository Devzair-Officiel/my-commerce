<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Entity\Review;
use App\Entity\User;
use App\Enum\ReviewStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * @return Review[]
     */
    public function findApprovedByProduct(Product $product): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.product = :product')
            ->andWhere('r.status = :status')
            ->setParameter('product', $product)
            ->setParameter('status', ReviewStatus::Approved)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne la note moyenne et le nombre d'avis approuvés pour chaque produit.
     * Résultat : [productId => ['average' => float, 'count' => int]]
     */
    /**
     * Retourne la note moyenne et le nombre d'avis approuvés pour chaque produit.
     * Si $productIds est vide, charge tous les produits (utilisé par le cache Twig).
     * Résultat : [productId => ['average' => float, 'count' => int]]
     */
    public function findRatingSummaryForProducts(array $productIds = []): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('IDENTITY(r.product) AS product_id, AVG(r.rating) AS average, COUNT(r.id) AS cnt')
            ->andWhere('r.status = :status')
            ->setParameter('status', ReviewStatus::Approved)
            ->groupBy('r.product');

        if (!empty($productIds)) {
            $qb->andWhere('r.product IN (:ids)')->setParameter('ids', $productIds);
        }

        $rows = $qb->getQuery()->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['product_id']] = [
                'average' => round((float) $row['average'], 1),
                'count'   => (int) $row['cnt'],
            ];
        }

        return $map;
    }

    public function findOneByProductAndUser(Product $product, User $user): ?Review
    {
        return $this->createQueryBuilder('r')
            ->where('r.product = :product')
            ->andWhere('r.user = :user')
            ->setParameter('product', $product)
            ->setParameter('user', $user)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
