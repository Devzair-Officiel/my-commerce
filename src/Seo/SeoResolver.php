<?php

namespace App\Seo;

use App\Entity\Blog;
use App\Entity\Category;
use App\Entity\Media;
use App\Entity\Product;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Construit un payload SEO prêt à être injecté dans les templates Twig.
 *
 * Rôle :
 * centraliser la logique SEO des pages indexables
 * (produit, catégorie, blog, page statique), afin d'éviter
 * de disperser les règles SEO dans les contrôleurs et les templates.
 *
 * Objectifs :
 * - garantir des fallbacks cohérents ;
 * - produire des URLs absolues propres ;
 * - générer des données structurées adaptées au type de page ;
 * - garder les contrôleurs simples et lisibles.
 */
final class SeoResolver
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $brandName = 'Nidemiel',
        private readonly string $defaultOgImage = '/assets/images/setting/nidemiel.png',
    ) {}

    /**
     * Construit le SEO complet pour une fiche produit.
     */
    public function forProduct(Product $product, Request $request): SeoPayload
    {
        $canonical = $product->getSeoCanonicalOverride()
            ?: $this->urlGenerator->generate(
                'app_product_by_slug',
                [
                    'slug' => $product->getSlug(),
                ],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

        $title = $product->getSeoTitle();
        if (!$title) {
            $origin = trim((string) $product->getOriginCountry());

            $title = $origin !== ''
                ? sprintf('%s – %s | %s', $product->getTitle(), $origin, $this->brandName)
                : sprintf('%s | %s', $product->getTitle(), $this->brandName);
        }

        $description = $product->getSeoDescription();
        if (!$description) {
            $sourceDescription = $product->getDescription()
                ?: $product->getMoreDescription()
                ?: sprintf('Découvrez %s chez %s.', $product->getTitle(), $this->brandName);

            $description = $this->truncateText($sourceDescription, 160);
        }

        $robots = $product->isSeoNoindex() ? 'noindex,follow' : 'index,follow';
        $image = $this->resolveProductImage($product, $request);

        $og = $this->buildOg(
            title: $title,
            description: $description,
            canonical: $canonical,
            image: $image,
            type: 'product'
        );

        $jsonLd = [
            $this->productJsonLd($product, $canonical, $image),
            $this->breadcrumbJsonLd([
                [
                    'name' => 'Accueil',
                    'url' => $this->absoluteRoute('app_home'),
                ],
                [
                    'name' => $product->getTitle(),
                    'url' => $canonical,
                ],
            ]),
        ];

        return new SeoPayload(
            $title,
            $description,
            $canonical,
            $robots,
            $og,
            $jsonLd
        );
    }

    /**
     * Construit le SEO complet pour une page catégorie.
     */
    public function forCategory(Category $category, Request $request): SeoPayload
    {
        $canonical = $category->getSeoCanonicalOverride()
            ?: $this->urlGenerator->generate(
                'app_category',
                ['categoryName' => $category->getSlug()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

        $title = $category->getSeoTitle()
            ?: sprintf('%s | %s', $category->getTitle(), $this->brandName);

        $description = $category->getSeoDescription();
        if (!$description) {
            $sourceDescription = $category->getDescription()
                ?: $category->getIntro()
                ?: sprintf('Découvrez notre sélection %s chez %s.', $category->getTitle(), $this->brandName);

            $description = $this->truncateText($sourceDescription, 160);
        }

        $robots = $category->isSeoNoindex() ? 'noindex,follow' : 'index,follow';
        $image = $this->resolveCategoryImage($category, $request);

        $og = $this->buildOg(
            title: $title,
            description: $description,
            canonical: $canonical,
            image: $image,
            type: 'website'
        );

        $jsonLd = [
            $this->collectionPageJsonLd(
                name: $category->getTitle(),
                description: $description,
                canonical: $canonical
            ),
            $this->breadcrumbJsonLd([
                [
                    'name' => 'Accueil',
                    'url' => $this->absoluteRoute('app_home'),
                ],
                [
                    'name' => $category->getTitle(),
                    'url' => $canonical,
                ],
            ]),
        ];

        return new SeoPayload($title, $description, $canonical, $robots, $og, $jsonLd);
    }

    /**
     * Construit le SEO complet pour un article de blog.
     */
    public function forBlog(Blog $blog, Request $request): SeoPayload
    {
        $canonical = $blog->getSeoCanonicalOverride()
            ?: $this->urlGenerator->generate(
                'app_blog_show',
                ['slug' => $blog->getSlug()],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

        $title = $blog->getSeoTitle()
            ?: sprintf('%s | Blog %s', $blog->getTitle(), $this->brandName);

        $description = $blog->getSeoDescription();
        if (!$description) {
            $sourceDescription = $blog->getDescription()
                ?: $blog->getContent()
                ?: sprintf('Découvrez notre article %s.', $blog->getTitle());

            $description = $this->truncateText($sourceDescription, 160);
        }

        $robots = $blog->isSeoNoindex() ? 'noindex,follow' : 'index,follow';
        $image = $this->resolveBlogImage($blog, $request);

        $og = $this->buildOg(
            title: $title,
            description: $description,
            canonical: $canonical,
            image: $image,
            type: 'article'
        );

        $jsonLd = [
            $this->blogPostingJsonLd($blog, $canonical, $image),
            $this->breadcrumbJsonLd([
                [
                    'name' => 'Accueil',
                    'url' => $this->absoluteRoute('app_home'),
                ],
                [
                    'name' => 'Blog',
                    'url' => $this->absoluteRoute('app_blog'),
                ],
                [
                    'name' => $blog->getTitle(),
                    'url' => $canonical,
                ],
            ]),
        ];

        return new SeoPayload($title, $description, $canonical, $robots, $og, $jsonLd);
    }

    /**
     * Construit le SEO d'une page statique sans dépendre d'une entité dédiée.
     *
     * Exemple :
     * livraison, contact, paiement, CGV, à propos...
     *
     * @param array{
     *     title: string,
     *     description: string,
     *     route?: string,
     *     routeParams?: array<string, scalar>,
     *     canonical?: string,
     *     robots?: string,
     *     ogImage?: string|array|null,
     *     ogType?: string,
     *     breadcrumbs?: array<int, array{name: string, url: string}>
     * } $data
     */
    public function forStaticPage(array $data, Request $request): SeoPayload
    {
        $canonical = $data['canonical']
            ?? $this->urlGenerator->generate(
                $data['route'],
                $data['routeParams'] ?? [],
                UrlGeneratorInterface::ABSOLUTE_URL
            );

        $title = $data['title'];
        $description = $this->truncateText($data['description'], 160);
        $robots = $data['robots'] ?? 'index,follow';

        $image = $this->toAbsoluteIfNeeded($data['ogImage'] ?? $this->defaultOgImage, $request);

        $og = $this->buildOg(
            title: $title,
            description: $description,
            canonical: $canonical,
            image: $image,
            type: $data['ogType'] ?? 'website'
        );

        $jsonLd = [
            $this->webPageJsonLd($title, $description, $canonical),
        ];

        if (!empty($data['breadcrumbs'])) {
            $jsonLd[] = $this->breadcrumbJsonLd($data['breadcrumbs']);
        }

        if (!empty($data['organization'])) {
            $jsonLd[] = $this->organizationJsonLd($data['organization']);
        }

        return new SeoPayload($title, $description, $canonical, $robots, $og, $jsonLd);
    }

    /**
     * Génère un fil d'Ariane structuré en JSON-LD.
     *
     * Objectif :
     * aider les moteurs à comprendre la hiérarchie logique de la page.
     *
     * @param array<int, array{name: string, url: string}> $items
     * @return array<string, mixed>
     */
    private function breadcrumbJsonLd(array $items): array
    {
        $list = [];

        foreach ($items as $index => $item) {
            $list[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    }

    /**
     * Génère les données structurées d'un produit simple.
     */
    private function productJsonLd(Product $product, string $canonical, ?string $image): array
    {
        $description = $product->getSeoDescription()
            ?: $product->getDescription()
            ?: $product->getMoreDescription()
            ?: $product->getTitle();

        $price = $product->getRegularPrice() !== null
            ? number_format($product->getRegularPrice() / 100, 2, '.', '')
            : null;

        $isAvailable = ($product->isAvailable() ?? true) && (($product->getStock() ?? 0) > 0);

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->getTitle(),
            'url' => $canonical,
            'description' => $this->truncateText(strip_tags((string) $description), 300),
            'brand' => [
                '@type' => 'Brand',
                'name' => $this->brandName,
            ],
        ];

        if ($image !== null) {
            $data['image'] = [$image];
        }

        if ($product->getWeightGrams() !== null) {
            $data['weight'] = [
                '@type' => 'QuantitativeValue',
                'value' => $product->getWeightGrams(),
                'unitCode' => 'GRM',
            ];
        }

        if ($price !== null) {
            $data['offers'] = [
                '@type' => 'Offer',
                'url' => $canonical,
                'priceCurrency' => 'EUR',
                'price' => $price,
                'priceValidUntil' => (new \DateTimeImmutable('+1 year'))->format('Y-m-d'),
                'availability' => $isAvailable
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => [
                    '@type' => 'Organization',
                    'name' => $this->brandName,
                ],
            ];
        }

        return $data;
    }

    /**
     * Génère les données structurées d'une page de collection.
     */
    private function collectionPageJsonLd(string $name, string $description, string $canonical): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'description' => $description,
            'url' => $canonical,
        ];
    }

    /**
     * Génère les données structurées d'un article de blog.
     */
    private function blogPostingJsonLd(Blog $blog, string $canonical, ?string $image): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $blog->getTitle(),
            'mainEntityOfPage' => $canonical,
            'datePublished' => $blog->getCreatedAt()?->format(\DATE_ATOM),
            'dateModified' => ($blog->getUpdatedAt() ?? $blog->getCreatedAt())?->format(\DATE_ATOM),
            'author' => [
                '@type' => 'Organization',
                'name' => $this->brandName,
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $this->brandName,
            ],
            'description' => $this->truncateText(
                $blog->getSeoDescription() ?: ($blog->getDescription() ?: strip_tags((string) $blog->getContent())),
                300
            ),
            'url' => $canonical,
        ];

        if ($image !== null) {
            $data['image'] = [$image];
        }

        return array_filter($data, static fn($value) => $value !== null);
    }

    /**
     * Construit le SEO de la page d'accueil avec Organisation + WebSite JSON-LD.
     */
    public function forHome(Request $request): SeoPayload
    {
        $homeUrl = $this->absoluteRoute('app_home');
        $searchUrl = rtrim($request->getSchemeAndHttpHost(), '/') . '/product/search?term={search_term_string}';

        $canonical = $homeUrl;
        $title = 'Miel naturel en ligne | Miels authentiques du monde | ' . $this->brandName;
        $description = 'Nidemiel vous propose des miels naturels et authentiques, sélectionnés pour leur origine, leur goût et leur qualité.';
        $robots = 'index,follow';
        $image = $this->toAbsoluteIfNeeded($this->defaultOgImage, $request);

        $og = $this->buildOg(
            title: $title,
            description: $description,
            canonical: $canonical,
            image: $image,
            type: 'website'
        );

        $jsonLd = [
            $this->webPageJsonLd($title, $description, $canonical),
            $this->webSiteJsonLd($homeUrl, $searchUrl),
            $this->organizationJsonLd([
                'name' => $this->brandName,
                'url' => $homeUrl,
                'logo' => rtrim($request->getSchemeAndHttpHost(), '/') . '/assets/images/setting/nidemiel.png',
            ]),
            $this->breadcrumbJsonLd([
                ['name' => 'Accueil', 'url' => $homeUrl],
            ]),
        ];

        return new SeoPayload($title, $description, $canonical, $robots, $og, $jsonLd);
    }

    /**
     * Génère les données structurées WebSite avec SearchAction.
     * Permet à Google d'afficher un champ de recherche directement dans ses résultats.
     */
    private function webSiteJsonLd(string $homeUrl, string $searchUrlTemplate): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'url' => $homeUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $searchUrlTemplate,
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * Génère les données structurées d'une organisation.
     *
     * @param array{name: string, url: string, logo?: string, email?: string, phone?: string, address?: string} $data
     */
    private function organizationJsonLd(array $data): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $data['name'],
            'url' => $data['url'],
        ];

        if (!empty($data['logo'])) {
            $schema['logo'] = $data['logo'];
        }

        if (!empty($data['email'])) {
            $schema['email'] = $data['email'];
        }

        if (!empty($data['phone'])) {
            $schema['telephone'] = $data['phone'];
        }

        if (!empty($data['address'])) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => $data['address'],
                'addressCountry' => 'FR',
            ];
        }

        return $schema;
    }

    /**
     * Génère les données structurées d'une page statique simple.
     */
    private function webPageJsonLd(string $title, string $description, string $canonical): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => $title,
            'description' => $description,
            'url' => $canonical,
        ];
    }

    /**
     * Construit le bloc Open Graph commun.
     *
     * @return array<string, string|null>
     */
    private function buildOg(
        string $title,
        string $description,
        string $canonical,
        ?string $image,
        string $type = 'website'
    ): array {
        return [
            'title' => $title,
            'description' => $description,
            'url' => $canonical,
            'type' => $type,
            'image' => $image,
        ];
    }

    /**
     * Génère une URL absolue à partir d'une route Symfony.
     *
     * @param array<string, scalar> $params
     */
    private function absoluteRoute(string $route, array $params = []): string
    {
        return $this->urlGenerator->generate($route, $params, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * Récupère l'image principale d'un produit.
     */
    private function resolveProductImage(Product $product, Request $request): ?string
    {
        if ($product->getSeoOgImage()) {
            return $this->toAbsoluteIfNeeded($product->getSeoOgImage(), $request);
        }

        foreach ($product->getMedias() as $media) {
            $path = $this->extractMediaPath($media);
            if ($path !== null) {
                return $this->toAbsoluteIfNeeded($path, $request);
            }
        }

        return $this->toAbsoluteIfNeeded($this->defaultOgImage, $request);
    }

    /**
     * Récupère l'image principale d'une catégorie.
     */
    private function resolveCategoryImage(Category $category, Request $request): ?string
    {
        if ($category->getSeoOgImage()) {
            return $this->toAbsoluteIfNeeded($category->getSeoOgImage(), $request);
        }

        $media = $category->getMedia();
        if ($media !== null) {
            $path = $this->extractMediaPath($media);
            if ($path !== null) {
                return $this->toAbsoluteIfNeeded($path, $request);
            }
        }

        return $this->toAbsoluteIfNeeded($this->defaultOgImage, $request);
    }

    /**
     * Récupère l'image principale d'un article de blog.
     */
    private function resolveBlogImage(Blog $blog, Request $request): ?string
    {
        if ($blog->getSeoOgImage()) {
            return $this->toAbsoluteIfNeeded($blog->getSeoOgImage(), $request);
        }

        foreach ($blog->getMedias() as $media) {
            $path = $this->extractMediaPath($media);
            if ($path !== null) {
                return $this->toAbsoluteIfNeeded($path, $request);
            }
        }

        return $this->toAbsoluteIfNeeded($this->defaultOgImage, $request);
    }

    /**
     * Extrait le chemin exploitable d'un média.
     *
     * Important :
     * cette méthode dépend de ta classe Media.
     * Si ton champ fichier ne s'appelle pas "filename",
     * adapte cette méthode.
     */
    private function extractMediaPath(Media $media): ?string
    {
        if (method_exists($media, 'getFilename') && $media->getFilename()) {
            return $media->getFilename();
        }

        return null;
    }

    /**
     * Transforme un chemin relatif en URL absolue.
     *
     * Accepte :
     * - une URL absolue ;
     * - un chemin relatif ;
     * - un tableau de chemins, dont le premier non vide sera utilisé.
     */
    private function toAbsoluteIfNeeded(string|array|null $pathOrUrl, Request $request): ?string
    {
        if ($pathOrUrl === null) {
            return null;
        }

        if (is_array($pathOrUrl)) {
            $pathOrUrl = array_values(array_filter(
                $pathOrUrl,
                static fn($value): bool => is_string($value) && trim($value) !== ''
            ))[0] ?? null;

            if ($pathOrUrl === null) {
                return null;
            }
        }

        $pathOrUrl = trim($pathOrUrl);

        if ($pathOrUrl === '') {
            return null;
        }

        if (str_starts_with($pathOrUrl, 'http://') || str_starts_with($pathOrUrl, 'https://')) {
            return $pathOrUrl;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/') . '/' . ltrim($pathOrUrl, '/');
    }

    /**
     * Nettoie et tronque un texte pour les titres, descriptions et JSON-LD.
     */
    private function truncateText(string $text, int $maxLength): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($text)) ?? '');

        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength - 1)) . '…';
    }
}
