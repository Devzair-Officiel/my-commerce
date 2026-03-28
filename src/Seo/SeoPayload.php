<?php

namespace App\Seo;

/**
 * Représente les données SEO finales d’une page.
 *
 * Ce payload est construit par SeoResolver puis transmis au template Twig
 * pour afficher les balises meta, Open Graph, canonical et JSON-LD.
 */
final class SeoPayload
{
    /**
     * @param array<string, mixed> $og
     * @param array<int, array<string, mixed>> $jsonLd
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description,
        public readonly ?string $canonical,
        public readonly string $robots,
        public readonly array $og = [],
        public readonly array $jsonLd = [],
    ) {}
}
