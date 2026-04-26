<?php

namespace App\Repository;

use App\Entity\FaqLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FaqLink>
 */
class FaqLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FaqLink::class);
    }

    /** @return FaqLink[] */
    public function findActive(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.isActive = true')
            ->orderBy('f.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
