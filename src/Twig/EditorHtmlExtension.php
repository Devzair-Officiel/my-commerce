<?php

namespace App\Twig;

use App\Service\EditorHtmlNormalizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Expose un filtre Twig pour normaliser ET sécuriser le HTML éditorial avant affichage.
 *
 * Rôle :
 * - fournir un point d'entrée Twig simple pour le HTML issu du back-office ;
 * - déléguer la normalisation au service EditorHtmlNormalizer ;
 * - appliquer le sanitizer en dernier : la sortie de ce filtre est la seule
 *   autorisée à être rendue en |raw. C'est la barrière XSS du contenu
 *   éditorial (produits, blog, pages), y compris pour le contenu historique
 *   déjà présent en base.
 */
final class EditorHtmlExtension extends AbstractExtension
{
    public function __construct(
        private readonly EditorHtmlNormalizer $editorHtmlNormalizer,
        private readonly HtmlSanitizerInterface $appEditorContentSanitizer,
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
     * Normalise puis sanitise le HTML fourni avant son rendu dans Twig.
     *
     * @param string|null $html HTML brut provenant de la base.
     *
     * @return string HTML normalisé et sûr pour un rendu |raw.
     */
    public function normalizeEditorHtml(?string $html): string
    {
        $normalized = $this->editorHtmlNormalizer->normalize($html);

        if ($normalized === '') {
            return '';
        }

        return $this->appEditorContentSanitizer->sanitize($normalized);
    }
}
