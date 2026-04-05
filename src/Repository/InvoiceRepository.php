<?php

namespace App\Repository;

use App\Entity\Invoice;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * Retourne le plus grand numéro séquentiel pour une année donnée.
     * Utilisé par InvoiceNumberGenerator.
     */
    public function findLastSequenceForYear(int $year): int
    {
        $prefix = sprintf('FAC-%d-', $year);

        $result = $this->createQueryBuilder('i')
            ->select('i.invoiceNumber')
            ->where('i.invoiceNumber LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('i.invoiceNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($result === null) {
            return 0;
        }

        // FAC-2026-0042 → 42
        $parts = explode('-', $result['invoiceNumber']);
        return (int) end($parts);
    }
}
