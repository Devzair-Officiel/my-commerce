<?php

namespace App\Repository;

use App\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Page>
 */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    /**
     * Pages header (scalaires uniquement)
     */
    public function findHeaderPagesForLayout(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.title AS title, p.slug AS slug, p.content AS content')
            ->andWhere('p.isHead = true')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Pages footer (scalaires uniquement)
     */
    public function findFooterPagesForLayout(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.title AS title, p.slug AS slug, p.content AS content')
            ->andWhere('p.isFoot = true')
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
