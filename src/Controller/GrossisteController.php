<?php

namespace App\Controller;

use App\Repository\FaqRepository;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Univers grossiste : page principale + fiches produits en gros.
 */
final class GrossisteController extends AbstractController
{
    public function __construct(
        private readonly ProductRepository $productRepo,
        private readonly FaqRepository $faqRepo,
    ) {}

    #[Route('/grossiste-miel', name: 'app_grossiste_index')]
    public function index(): Response
    {
        $products = $this->productRepo->findWholesaleProducts();
        $faqs     = $this->faqRepo->findActiveWholesale();

        return $this->render('grossiste/index.html.twig', [
            'products' => $products,
            'faqs'     => $faqs,
        ]);
    }

    #[Route('/grossiste-miel/{slug}', name: 'app_grossiste_product', requirements: ['slug' => '[a-z0-9-]+'])]
    public function showProduct(string $slug): Response
    {
        // Le slug grossiste se termine par "-en-gros", on retrouve le produit retail via le slug de base
        $retailSlug = preg_replace('/-en-gros$/', '', $slug);

        $product = $this->productRepo->findOneBySlugWithRelations($retailSlug);

        if (!$product) {
            throw $this->createNotFoundException('Produit grossiste introuvable.');
        }

        // Images dédiées grossiste ; fallback sur toutes les images si aucune marquée
        $allMedias       = $product->getMedias()->filter(fn($m) => is_string($m->getFilename()) && $m->getFilename() !== '');
        $wholesaleMedias = $allMedias->filter(fn($m) => $m->isWholesale() === true);
        $media           = array_values(($wholesaleMedias->count() > 0 ? $wholesaleMedias : $allMedias)->toArray());

        return $this->render('grossiste/show.html.twig', [
            'product'         => $product,
            'grossisteSlug'   => $slug,
            'media'           => $media,
            'relatedProducts' => $product->getRelatedProducts(),
        ]);
    }
}
