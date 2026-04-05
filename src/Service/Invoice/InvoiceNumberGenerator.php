<?php

namespace App\Service\Invoice;

use App\Repository\InvoiceRepository;

/**
 * Génère des numéros de facture séquentiels et sans trou : FAC-YYYY-XXXX.
 * La séquence repart à 1 chaque année.
 */
final class InvoiceNumberGenerator
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
    ) {}

    public function generate(): string
    {
        $year = (int) (new \DateTimeImmutable())->format('Y');
        $last = $this->invoiceRepository->findLastSequenceForYear($year);

        return sprintf('FAC-%d-%04d', $year, $last + 1);
    }
}
