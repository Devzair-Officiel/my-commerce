<?php

namespace App\Repository;

use App\Entity\Faq;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Faq>
 */
class FaqRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Faq::class);
    }

    /** @return Faq[] FAQ particuliers (non grossiste) */
    public function findActive(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.isActive = true')
            ->andWhere('f.isWholesale = false')
            ->orderBy('f.section', 'ASC')
            ->addOrderBy('f.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return array<string, Faq[]> FAQs groupées par section */
    public function findActiveGroupedBySection(): array
    {
        $faqs = $this->findActive();
        $grouped = [];
        foreach ($faqs as $faq) {
            $section = $faq->getSection() ?? '';
            $grouped[$section][] = $faq;
        }
        return $grouped;
    }

    /** @return Faq[] FAQ dédiée à la page grossiste */
    public function findActiveWholesale(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.isActive = true')
            ->andWhere('f.isWholesale = true')
            ->orderBy('f.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
