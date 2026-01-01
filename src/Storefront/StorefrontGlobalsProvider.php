<?php

namespace App\Storefront;

use App\Repository\CategoryRepository;
use App\Repository\PageRepository;
use App\Repository\SettingRepository;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class StorefrontGlobalsProvider
{
    public const TAG_GLOBALS    = 'storefront.globals';
    public const TAG_SETTING    = 'storefront.setting';
    public const TAG_PAGES      = 'storefront.pages';
    public const TAG_CATEGORIES = 'storefront.categories';

    private const CACHE_KEY = 'storefront.globals.v3.scalars';

    public function __construct(
        private readonly SettingRepository $settings,
        private readonly PageRepository $pages,
        private readonly CategoryRepository $categories,
        private readonly TagAwareCacheInterface $cacheStorefront, // @cache.storefront
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
            ]);

            return [
                'globalSetting' => $this->settings->findOneForLayout(),
                'globalHeaderPages' => $this->pages->findHeaderPagesForLayout(),
                'globalFooterPages' => $this->pages->findFooterPagesForLayout(),
                'globalMegaCategories' => $this->categories->findMegaCategoriesForLayout(),
            ];
        });
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

    public function invalidateAll(): void
    {
        $this->cacheStorefront->invalidateTags([self::TAG_GLOBALS]);
    }
}
