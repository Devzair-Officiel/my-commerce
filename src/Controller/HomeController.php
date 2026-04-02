<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\SlidersRepository;
use App\Seo\SeoResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class HomeController extends AbstractController
{
    public function __construct(private ProductRepository $product) 
    {}

    #[Route('/', name: 'app_home')]
    public function index(SlidersRepository $slider, SeoResolver $seoResolver, Request $request): Response
    {
        $sliders = $slider->findAll();

        $seo = $seoResolver->forStaticPage([
            'title' => 'Miel naturel en ligne | Miels authentiques du monde | Nidemiel',
            'description' => 'Nidemiel vous propose des miels naturels et authentiques, sélectionnés pour leur origine, leur goût et leur qualité.',
            'canonical' => $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
            'robots' => 'index,follow',
            'ogType' => 'website',
            'breadcrumbs' => [
                [
                    'name' => 'Accueil',
                    'url' => $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL),
                ],
            ],
        ], $request);

        return $this->render('home/index.html.twig', [
            'sliders' => $sliders,
            'productBestSeller' => $this->product->findFeaturedWithMedias('isBestSeller'),
            'productNewArrival' => $this->product->findFeaturedWithMedias('isNewArrival'),
            'productAll'        => $this->product->findFeaturedWithMedias('isAvailable'),
            'seo' => $seo,
        ]);
    }
}
