<?php

namespace App\Service\Invoice;

use App\Repository\InvoiceRepository;
use Symfony\Component\Lock\LockFactory;

/**
 * Génère des numéros de facture séquentiels et sans trou : FAC-YYYY-XXXX.
 * La séquence repart à 1 chaque année.
 *
 * Un verrou exclusif est acquis le temps de la génération pour éviter
 * qu'un doublon soit produit en cas d'appels simultanés.
 */
final class InvoiceNumberGenerator
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly LockFactory $lockFactory,
    ) {}

    public function generate(): string
    {
        $lock = $this->lockFactory->createLock('invoice_number_generation', ttl: 5);
        $lock->acquire(blocking: true);

        try {
            $year = (int) (new \DateTimeImmutable())->format('Y');
            $last = $this->invoiceRepository->findLastSequenceForYear($year);

            return sprintf('FAC-%d-%04d', $year, $last + 1);
        } finally {
            $lock->release();
        }
    }
}
