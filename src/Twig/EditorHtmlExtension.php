<?php

namespace App\Twig;

use App\Service\EditorHtmlNormalizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Expose un filtre Twig pour normaliser le HTML éditorial avant affichage.
 *
 * Rôle :
 * - fournir un point d'entrée Twig simple pour le HTML issu du back-office ;
 * - déléguer la normalisation au service EditorHtmlNormalizer.
 *
 * Objectif :
 * garder les templates Twig lisibles tout en centralisant la logique
 * de transformation du HTML dans un service dédié.
 */
final class EditorHtmlExtension extends AbstractExtension
{
    public function __construct(
        private readonly EditorHtmlNormalizer $editorHtmlNormalizer,
    ) {}

    /**
     * Déclare les filtres Twig disponibles.
     *
     * @return array<int, TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('normalize_editor_html', [$this, 'normalizeEditorHtml']),
        ];
    }

    /**
     * Normalise le HTML fourni avant son rendu dans Twig.
     *
     * @param string|null $html HTML brut provenant de la base.
     *
     * @return string HTML normalisé.
     */
    public function normalizeEditorHtml(?string $html): string
    {
        return $this->editorHtmlNormalizer->normalize($html);
    }
}
