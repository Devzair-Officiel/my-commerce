<?php

namespace App\Controller;

use App\Seo\SeoResolver;
use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

final class ProductController extends AbstractController
{

    public function __construct(private ProductRepository $productRepo) {}
    
    #[Route('/produits/{catalog}/{slug}', name: 'app_product_by_slug', requirements: [
        'catalog' => '[a-z0-9-]+',
        'slug' => '[a-z0-9-]+',
    ])]
    public function showProduct(string $slug, string $catalog, SeoResolver $seoResolver, Request $request)
    {
        $product = $this->productRepo->findOneBy(['slug' => $slug, 'catalog' => $catalog]);


        if (!$product) {
            return $this->render('page/not-fount.html.twig', [
                'controller_name' => 'PageController'
            ]);
        }

        $seo = $seoResolver->forProduct($product, $request);

        return $this->render('product/show_product_by_slug.html.twig', [
            'product' => $product,
            'media' => $product->getMediaData(),
            'productBestSeller' => $this->productRepo->findBy(['isBestSeller' => true]),
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
    public function searchProduct(Request $request)
    {
        $search = $request->query->get('term');

        $products = $this->productRepo->search($search);


        return $this->render('product/search.html.twig', [
            'products' => $products,
            'search' => $search,
        ]);
    }

    #[Route('/category/{categoryName}', name: 'app_category')]
    public function getProductByCategory(CategoryRepository $categoryRepository, string $categoryName)
    {

        // Récupérez l'objet Category en fonction du nom de la catégorie
        $category = $categoryRepository->findOneBy(['slug' => $categoryName]);

        // Vérifiez si la catégorie existe
        if (!$category) {
            throw $this->createNotFoundException('La catégorie demandée n\'existe pas');
        }

        // Récupérez l'ID de la catégorie
        $categoryId = $category->getId();

        // Utilisez l'ID de la catégorie pour récupérer les product
        $products = $this->productRepo->getByCategories($categoryId);

        // Passez les product à votre vue
        return $this->render('product/category.html.twig', [
            'products' => $products,
            'category' => $category,
        ]);
    }

    #[Route('/error', name: 'app_error')]
    public function errorPage()
    {
        return $this->render('page/not-fount.html.twig');
    }
}
