<?php

namespace App\Service;

use App\Entity\HoneyTasting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Génère le contenu binaire PDF d'une fiche de dégustation de miel.
 */
final class HoneyTastingPdfRenderer
{
    public function __construct(
        private readonly Environment $twig,
        private readonly HoneyTastingSchema $schema,
    ) {}

    public function render(HoneyTasting $tasting): string
    {
        $html = $this->twig->render('tasting_notebook/pdf.html.twig', [
            'tasting' => $tasting,
            'grouped' => $this->schema->groupForDisplay($tasting->getData()),
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

    public function getFilename(HoneyTasting $tasting): string
    {
        $slug = preg_replace('/[^a-z0-9]+/i', '-', (string) $tasting->getTasterName()) ?: 'anonyme';
        $slug = trim(strtolower($slug), '-');
        $date = $tasting->getCreatedAt()?->format('Y-m-d') ?? date('Y-m-d');

        return sprintf('carnet-degustation-%s-%s.pdf', $slug, $date);
    }
}
