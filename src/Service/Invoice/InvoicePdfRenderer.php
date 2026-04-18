<?php

namespace App\Service\Invoice;

use App\Entity\Invoice;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

/**
 * Génère le contenu binaire PDF d'une facture.
 */
final class InvoicePdfRenderer
{
    private string $publicDir;

    public function __construct(
        private readonly Environment $twig,
        ParameterBagInterface $params,
    ) {
        $this->publicDir = $params->get('kernel.project_dir') . '/public';
    }

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

        $logoBase64 = '';
        if (!empty($seller['logo_filename'])) {
            $abs = $this->publicDir . '/assets/images/setting/' . $seller['logo_filename'];
            if (file_exists($abs)) {
                $mime = mime_content_type($abs) ?: 'image/png';
                $logoBase64 = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($abs));
            }
        }

        $html = $this->twig->render('invoice/pdf.html.twig', [
            'invoice'        => $invoice,
            'order'          => $order,
            'seller'         => $seller,
            'billingAddress' => $billingAddress,
            'taxAmountCents' => max(0, $taxAmountCents ?? 0),
            'logoPath'       => $logoBase64,
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
