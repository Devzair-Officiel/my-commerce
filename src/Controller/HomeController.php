<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\SlidersRepository;
use App\Seo\SeoResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(private ProductRepository $product) 
    {}

    #[Route('/', name: 'app_home')]
    public function index(SlidersRepository $slider, SeoResolver $seoResolver, Request $request): Response
    {
        $sliders = $slider->findAll();

        $seo = $seoResolver->forHome($request);

        return $this->render('home/index.html.twig', [
            'sliders' => $sliders,
            'productBestSeller' => $this->product->findFeaturedWithMedias('isBestSeller'),
            'productNewArrival' => $this->product->findFeaturedWithMedias('isNewArrival'),
            'productAll'        => $this->product->findFeaturedWithMedias('isAvailable'),
            'seo' => $seo,
        ]);
    }
}
