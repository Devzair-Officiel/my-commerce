<?php

namespace App\Repository;

use App\Entity\Carrier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Carrier>
 */
class CarrierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Carrier::class);
    }

    public function findOneById(mixed $id): ?Carrier
    {
        if ($id === null) {
            return null;
        }

        return $this->find((int) $id);
    }
}
