<?php

namespace App\Repository;

use App\Entity\HoneyTasting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HoneyTasting>
 */
class HoneyTastingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HoneyTasting::class);
    }

    /**
     * Recherche insensible à la casse d'une fiche par nom du dégustateur.
     * Utilise l'index fonctionnel unique posé sur LOWER(taster_name).
     */
    public function findOneByNameCaseInsensitive(string $name): ?HoneyTasting
    {
        $normalized = mb_strtolower(trim($name));
        if ($normalized === '') {
            return null;
        }

        return $this->createQueryBuilder('t')
            ->andWhere('LOWER(t.tasterName) = :name')
            ->setParameter('name', $normalized)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
