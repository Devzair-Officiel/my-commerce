<?php

namespace App\Storefront;

use App\Repository\CategoryRepository;
use App\Repository\PageRepository;
use App\Repository\ProductRepository;
use App\Repository\SettingRepository;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Fournit les données globales du storefront (header, footer, settings)
 * sous forme de tableaux scalaires, avec cache taggable.
 *
 * Objectifs :
 * - éviter l'utilisation de la session pour des données publiques
 * - éviter le cache d'entités Doctrine (zéro entité en cache)
 * - centraliser les données du layout (header/footer)
 * - invalider automatiquement le cache lors des modifications en admin
 */
final class StorefrontGlobalsProvider
{
    public const TAG_GLOBALS    = 'storefront.globals';
    public const TAG_SETTING    = 'storefront.setting';
    public const TAG_PAGES      = 'storefront.pages';
    public const TAG_CATEGORIES = 'storefront.categories';
    public const TAG_PRODUCTS   = 'storefront.products';

    // Clé versionnée : incrémenter la version si la structure change
    private const CACHE_KEY = 'storefront.globals.v9.objects';

    private const MEGA_MENU_PRODUCTS_PER_CATEGORY = 6;

    public function __construct(
        private readonly SettingRepository $settings,
        private readonly PageRepository $pages,
        private readonly CategoryRepository $categories,
        private readonly ProductRepository $products,
        private readonly TagAwareCacheInterface $cacheStorefront,
    ) {}

    public function getGlobals(): array
    {
        return $this->cacheStorefront->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(3600);

            $item->tag([
                self::TAG_GLOBALS,
                self::TAG_SETTING,
                self::TAG_PAGES,
                self::TAG_CATEGORIES,
                self::TAG_PRODUCTS,
            ]);

            $megaCategories = $this->categories->findMegaCategoriesForLayout();
            $categoryIds = array_map(
                static fn(\App\Entity\Category $c): int => (int) $c->getId(),
                $megaCategories
            );
            $categoryIds = array_values(array_filter($categoryIds, static fn(int $id) => $id > 0));

            $productsRows = $this->products->findByCategoriesForLayout($categoryIds);

            // Regroupement + limite par catégorie côté PHP (simple et robuste)
            $productsByCategory = [];
            foreach ($productsRows as $row) {
                $catId = (int) $row['category_id'];

                if (!isset($productsByCategory[$catId])) {
                    $productsByCategory[$catId] = [];
                }

                if (\count($productsByCategory[$catId]) >= self::MEGA_MENU_PRODUCTS_PER_CATEGORY) {
                    continue;
                }

                $productsByCategory[$catId][] = $row;
            }

            return [
                'globalSetting'        => $this->settings->findOneForLayout() ?? $this->defaultSetting(),
                'globalHeaderPages'    => $this->pages->findHeaderPagesForLayout(),
                'globalFooterPages'    => $this->pages->findFooterPagesForLayout(),
                'globalMegaCategories' => $megaCategories,
                'globalMegaProducts'   => $productsByCategory,
            ];
        });
    }

    private function defaultSetting(): array
    {
        return [
            'id'                  => null,
            'website_name'        => 'Mon site',
            'description'         => '',
            'currency'            => 'EUR',
            'taxe_rate'           => 0,
            'street'              => '',
            'city'                => '',
            'code_postal'         => '',
            'state'               => '',
            'phone'               => '',
            'email'               => null,
            'facebookLink'        => null,
            'instaLink'           => null,
            'youtubeLink'         => null,
            'copyright'           => null,
            'copyrightUrl'        => null,
            'logo_filename'       => null,
            'logo_alt'            => null,
            'favicon_filename'    => null,
            'hero_video_filename' => null,
            'hero_poster_filename'=> null,
            'heroTitle'           => null,
            'heroButtonText'      => null,
            'heroButtonUrl'       => null,
        ];
    }

    public function invalidateSetting(): void
    {
        $this->cacheStorefront->invalidateTags([self::TAG_SETTING]);
    }

    public function invalidatePages(): void
    {
        $this->cacheStorefront->invalidateTags([self::TAG_PAGES]);
    }

    public function invalidateCategories(): void
    {
        $this->cacheStorefront->invalidateTags([self::TAG_CATEGORIES]);
    }

    public function invalidateProducts(): void
    {
        $this->cacheStorefront->invalidateTags([self::TAG_PRODUCTS]);
    }

    public function invalidateAll(): void
    {
        $this->cacheStorefront->invalidateTags([self::TAG_GLOBALS]);
    }
}
