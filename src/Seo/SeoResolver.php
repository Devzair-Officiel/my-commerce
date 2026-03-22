<?php

namespace App\Seo;

use App\Entity\Product;
use App\Seo\SeoPayload;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Construit un payload SEO "final" pour une page (meta title/description, canonical,
 * OpenGraph, robots) + JSON-LD (schema.org).
 *
 * Objectif : centraliser la logique SEO (fallbacks, normalisation d'URL/images),
 * afin d'éviter de disperser les règles dans les contrôleurs et les templates.
 */
final class SeoResolver
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $brandName = 'Nidemiel',
        private readonly string $defaultOgImage = '/images/og-default.jpg',
    ) {}

    /**
     * Génère le SEO complet pour une fiche produit.
     */
    public function forProduct(Product $product, Request $request): SeoPayload
    {

        // Canonical: route produit
        $canonical = $product->getSeoCanonicalOverride()
            ?: $this->urlGenerator->generate(
                'app_product_by_slug',
                [
                    'catalog' => $product->getCatalog(),
                    'slug' => $product->getSlug(),
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

        // Title
        $title = $product->getSeoTitle();
        if (!$title) {
            $origin = trim(($product->getOriginCountry() ?? ''));
            $title = $origin
                ? sprintf('%s – %s | %s', $product->getTitle(), $origin, $this->brandName)
                : sprintf('%s | %s', $product->getTitle(), $this->brandName);
        }

        // Description
        $description = $product->getSeoDescription();
        if (!$description) {
            $bits = [];
            $bits[] = 'Miel premium';
            if ($product->getOriginCountry()) $bits[] = 'origine ' . $product->getOriginCountry();
            $bits[] = 'pureté et goût d’exception';
            $description = ucfirst(implode(', ', array_unique($bits))) . '.';
        }

        // Robots
        $robots = $product->isSeoNoindex() ? 'noindex,follow' : 'index,follow';

        // OG Image: priorité SEO Image > image produit > defaut
        $ogImageRow = $product->getSeoOgImage()
            ?: ($product->getMediaFilenames() ?: $this->defaultOgImage);

        $ogImageAbs = $this->toAbsoluteIfNeeded($ogImageRow, $request);

        $og = [
            'title' => $title,
            'description' => $description,
            'url' => $canonical,
            'type' => 'product',
            'image' => $ogImageAbs,
        ];

        $jsonLd = [
            $this->productJsonLd($product, $canonical, $ogImageAbs, $this->brandName),
            $this->breadcrumbJsonLd($canonical, $product->getTitle()),
        ];


        // (Optionnel) FAQPage si tu as une FAQ (ici je laisse l’endroit où l’ajouter)
        // $jsonLd[] = $this->faqJsonLd($product);

        return new SeoPayload($title, $description, $canonical, $robots, $og, $jsonLd);
    }

    /**
     * JSON-LD schema.org pour un produit simple (pas de déclinaisons).
     * Si tu as stock/prix/sku, c’est ici qu’on les alimente.
     */
    private function productJsonLd(Product $product, string $canonical, ?string $absImage, string $siteName): array
    {
        $description = $product->getSeoDescription() ?: ($product->getDescription() ?: $product->getTitle());

        $regularPrice = $product->getRegularPrice();

        $price = $regularPrice !== null
            ? number_format($regularPrice / 100, 2, '.', '')
            : null;

        $inStock = method_exists($product, 'isInStock') ? (bool)$product->isInStock() : true;

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->getTitle(),
            'description' => mb_substr(strip_tags((string)$description), 0, 300),
            'image' => $absImage ? [$absImage] : [],
            'brand' => ['@type' => 'Brand', 'name' => $this->brandName],
        ];

        if ($price !== null) {
            $data['offers'] = [
                '@type' => 'Offer',
                'url' => $canonical,
                'priceCurrency' => 'EUR',
                'price' => $price,
                'availability' => $inStock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
            ];
        }

        return $data;
    }

    /**
     * BreadcrumbList JSON-LD pour améliorer la compréhension de la hiérarchie.
     */
    private function breadcrumbJsonLd(string $canonical, string $productName): array
    {
        $home = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $miels = $this->urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => $home],
                ['@type' => 'ListItem', 'position' => 2, 'name' => 'Miels', 'item' => $miels],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $productName, 'item' => $canonical],
            ],
        ];
    }

    /**
     * Normalise une image (ou un chemin) en URL absolue.
     * Accepte string|array|null (utile si ton modèle stocke plusieurs images).
     */
    private function toAbsoluteIfNeeded(string|array|null $pathOrUrl, Request $request): ?string
    {
        if ($pathOrUrl === null) {
            return null;
        }

        if (is_array($pathOrUrl)) {
            $pathOrUrl = array_values(
                array_filter($pathOrUrl, fn($v) => is_string($v) && trim($v) !== '')
            )[0] ?? null;

            if ($pathOrUrl === null) {
                return null;
            }
        }

        $pathOrUrl = trim($pathOrUrl);

        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            return $pathOrUrl;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/' . ltrim($pathOrUrl, '/');
    }
}
