<?php

namespace App\Repository;

use App\Entity\Shipment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Shipment>
 */
class ShipmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shipment::class);
    }

//    /**
//     * @return Shipment[] Returns an array of Shipment objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

    /**
     * Retourne tous les colis expédiés mais pas encore livrés, triés du plus ancien au plus récent.
     *
     * @return Shipment[]
     */
    public function findActiveShipments(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.shippedAt IS NOT NULL')
            ->andWhere('s.deliveredAt IS NULL')
            ->andWhere('s.trackingNumber IS NOT NULL')
            ->orderBy('s.shippedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
