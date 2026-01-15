<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * Retourne les commandes paginées d’un utilisateur + le total.
     *
     * @param \App\Entity\User $user
     * @param int $page
     * @param int $limit
     * @return array{orders: Order[], total: int}
     */
    public function findPaginatedByUser(\App\Entity\User $user, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.id', 'DESC');

        $query = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();

        $orders = $query->getResult();

        // Total pour pagination
        $qbCount = clone $qb;
        $qbCount->resetDQLPart('orderBy');
        $qbCount->select('COUNT(o.id)')
            ->setFirstResult(null)
            ->setMaxResults(null);
        $total = (int) $qbCount->getQuery()->getSingleScalarResult();

        return [
            'orders' => $orders,
            'total' => $total,
        ];
    }

    //    /**
    //     * @return Order[] Returns an array of Order objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Order
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
