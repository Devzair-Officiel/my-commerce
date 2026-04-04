<?php

namespace App\Controller;

use App\Repository\BlogRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class SitemapController extends AbstractController
{
    public function __construct(
        private ProductRepository  $productRepo,
        private CategoryRepository $categoryRepo,
        private BlogRepository     $blogRepo,
    ) {}

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'], defaults: ['_format' => 'xml'])]
    public function sitemap(UrlGeneratorInterface $urlGenerator): Response
    {
        $urls = [];

        // Pages statiques
        $statics = [
            ['route' => 'app_home',    'priority' => '1.0', 'changefreq' => 'weekly'],
            ['route' => 'app_blog',    'priority' => '0.8', 'changefreq' => 'weekly'],
            ['route' => 'app_contact', 'priority' => '0.5', 'changefreq' => 'monthly'],
        ];

        foreach ($statics as $s) {
            $urls[] = [
                'loc'        => $urlGenerator->generate($s['route'], [], UrlGeneratorInterface::ABSOLUTE_URL),
                'priority'   => $s['priority'],
                'changefreq' => $s['changefreq'],
            ];
        }

        // Catégories
        foreach ($this->categoryRepo->findAll() as $category) {
            if (!$category->getSlug()) continue;
            $urls[] = [
                'loc'        => $urlGenerator->generate('app_category', ['categoryName' => $category->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod'    => null,
                'priority'   => '0.8',
                'changefreq' => 'weekly',
            ];
        }

        // Produits disponibles
        foreach ($this->productRepo->findBy(['isAvailable' => true]) as $product) {
            if (!$product->getSlug()) continue;
            $urls[] = [
                'loc'        => $urlGenerator->generate('app_product_by_slug', ['slug' => $product->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod'    => $product->getUpdatedAt()?->format('Y-m-d'),
                'priority'   => '0.9',
                'changefreq' => 'weekly',
            ];
        }

        // Articles de blog
        foreach ($this->blogRepo->findAll() as $blog) {
            if (!$blog->getSlug()) continue;
            $urls[] = [
                'loc'        => $urlGenerator->generate('app_blog_show', ['slug' => $blog->getSlug()], UrlGeneratorInterface::ABSOLUTE_URL),
                'lastmod'    => ($blog->getUpdatedAt() ?? $blog->getCreatedAt())?->format('Y-m-d'),
                'priority'   => '0.7',
                'changefreq' => 'monthly',
            ];
        }

        $response = new Response(
            $this->renderView('sitemap/index.xml.twig', ['urls' => $urls]),
            200,
            ['Content-Type' => 'application/xml; charset=UTF-8']
        );

        $response->setPublic();
        $response->setSharedMaxAge(3600); // cache 1h

        return $response;
    }

    #[Route('/robots.txt', name: 'app_robots', methods: ['GET'])]
    public function robots(UrlGeneratorInterface $urlGenerator): Response
    {
        $sitemap = $urlGenerator->generate('app_sitemap', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $content = <<<TXT
        User-agent: *
        Allow: /

        Disallow: /admin
        Disallow: /account
        Disallow: /cart
        Disallow: /checkout
        Disallow: /login
        Disallow: /register
        Disallow: /logout
        Disallow: /reset-password
        Disallow: /api/
        Disallow: /product/search

        Sitemap: {$sitemap}
        TXT;

        return new Response(
            trim(preg_replace('/^        /m', '', $content)),
            200,
            ['Content-Type' => 'text/plain']
        );
    }
}
