<?php

namespace App\Service\Invoice;

use App\Entity\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Génère le contenu binaire PDF d'une facture.
 */
final class InvoicePdfRenderer
{
    public function __construct(
        private readonly Environment $twig,
    ) {}

    /**
     * Retourne le contenu binaire du PDF.
     */
    public function render(Invoice $invoice): string
    {
        $order  = $invoice->getCustomerOrder();
        $seller = $invoice->getSellerSnapshot() ?? [];

        $billingAddress = null;
        if ($order !== null && $order->getBillingAddress() !== null) {
            $billingAddress = json_decode($order->getBillingAddress(), true) ?: null;
        }

        $taxAmountCents = $order?->getTaxAmountCents();
        // Calcul de secours si taxAmountCents n'est pas stocké
        if ($taxAmountCents === null && $order !== null) {
            $taxAmountCents = $order->getOrderTotalTtcCents() - $order->getItemsTotalHtCents() - $order->getCarrierPriceSnapshotCents();
        }

        $html = $this->twig->render('invoice/pdf.html.twig', [
            'invoice'        => $invoice,
            'order'          => $order,
            'seller'         => $seller,
            'billingAddress' => $billingAddress,
            'taxAmountCents' => max(0, $taxAmountCents ?? 0),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Retourne le nom de fichier suggéré pour le PDF.
     */
    public function getFilename(Invoice $invoice): string
    {
        return 'facture-' . $invoice->getInvoiceNumber() . '.pdf';
    }
}
