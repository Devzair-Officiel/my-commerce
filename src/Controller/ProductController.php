<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
use App\Repository\ProductRepository;
use App\Seo\SeoResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Attribute\Route;

final class ProductController extends AbstractController
{

    public function __construct(private ProductRepository $productRepo) {}
    
    #[Route('/produits/{slug}', name: 'app_product_by_slug', requirements: [
        'slug' => '[a-z0-9-]+',
    ])]
    public function showProduct(string $slug, SeoResolver $seoResolver, Request $request): Response
    {
        $product = $this->productRepo->findOneBySlugWithRelations($slug);

        if (!$product) {
            return $this->render('page/not-fount.html.twig');
        }

        $seo = $seoResolver->forProduct($product, $request);

        return $this->render('product/show_product_by_slug.html.twig', [
            'product' => $product,
            'media' => $product->getMediaData(),
            'productBestSeller' => $this->productRepo->findBestSellersWithMedias(),
            'relatedProducts' => $product->getRelatedProducts(),
            'seo' => $seo
        ]);
    }

    #[Route('/product/get/{id}', name: 'app_product_by_id')]
    public function getProduct(int $id)
    {
        $product = $this->productRepo->findOneBy(['id' => $id]);

        if (!$product) {
            return $this->json(false);
        }

        return $this->json([
            'id' => $product->getId(),
            'title' => $product->getTitle(),
            'image' => $product->getMediaFilenames(),
            'stock' => $product->getStock(),
            'soldePrice' => $product->getSoldePrice(),
            'regularPrice' => $product->getRegularPrice(),
        ]);
    }

    #[Route('/product/search', name: 'app_search')]
    public function searchProduct(Request $request, SeoResolver $seoResolver): Response
    {
        $term     = trim((string) $request->query->get('term', ''));
        $products = $term !== '' ? $this->productRepo->search($term) : [];

        $seo = $seoResolver->forStaticPage([
            'title'       => $term !== ''
                ? sprintf('Recherche "%s" | Nidemiel', $term)
                : 'Recherche | Nidemiel',
            'description' => $term !== ''
                ? sprintf('Résultats de recherche pour "%s" sur Nidemiel. Découvrez nos produits naturels.', $term)
                : 'Recherchez parmi nos produits naturels sur Nidemiel.',
            'canonical'   => $request->getUri(),
            'robots'      => 'noindex,follow',
            'breadcrumbs' => [
                ['name' => 'Accueil', 'url' => $this->generateUrl('app_home', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL)],
                ['name' => $term !== '' ? 'Recherche : ' . $term : 'Recherche', 'url' => $request->getUri()],
            ],
        ], $request);

        return $this->render('product/search.html.twig', [
            'products' => $products,
            'search'   => $term,
            'seo'      => $seo,
        ]);
    }

    #[Route('/api/search', name: 'api_search', methods: ['GET'])]
    public function searchAutocomplete(
        Request $request,
        #[Autowire(service: 'limiter.search')] RateLimiterFactory $limiter,
    ): JsonResponse {
        $limit = $limiter->create($request->getClientIp() ?? 'anon');
        if (!$limit->consume(1)->isAccepted()) {
            return $this->json(['results' => []], 429);
        }

        $term = trim((string) $request->query->get('term', ''));

        if (mb_strlen($term) < 2) {
            return $this->json(['results' => []]);
        }

        $products = $this->productRepo->search($term, 6);

        $results = array_map(function ($product) {
            $mediaData = $product->getMediaData();
            $filename  = $mediaData[0]['filename'] ?? null;
            $info      = $filename ? pathinfo($filename) : null;
            $thumb     = $info
                ? ($info['filename'] . '-thumb.' . ($info['extension'] ?? 'jpg'))
                : null;

            return [
                'id'    => $product->getId(),
                'title' => $product->getTitle(),
                'slug'  => $product->getSlug(),
                'price' => $product->isOnSale()
                    ? $product->getSoldePrice()
                    : $product->getRegularPrice(),
                'isOnSale'   => $product->isOnSale(),
                'thumb'      => $thumb,
                'thumbAlt'   => $mediaData[0]['alt'] ?? null,
            ];
        }, $products);

        return $this->json(['results' => $results]);
    }

    #[Route('/category/{categoryName}', name: 'app_category')]
    public function getProductByCategory(CategoryRepository $categoryRepository, string $categoryName, SeoResolver $seoResolver, Request $request)
    {

        // Récupérez l'objet Category en fonction du nom de la catégorie
        $category = $categoryRepository->findOneBy(['slug' => $categoryName]);

        // Vérifiez si la catégorie existe
        if (!$category) {
            throw $this->createNotFoundException('La catégorie demandée n\'existe pas');
        }

        $products = $this->productRepo->getByCategories((int) $category->getId());
        $seo = $seoResolver->forCategory($category, $request);

        // Passez les product à votre vue
        return $this->render('product/category.html.twig', [
            'products' => $products,
            'category' => $category,
            'seo' => $seo
        ]);
    }

    #[Route('/error', name: 'app_error')]
    public function errorPage()
    {
        return $this->render('page/not-fount.html.twig');
    }
}
