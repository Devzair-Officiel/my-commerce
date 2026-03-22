<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\SlidersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(private ProductRepository $product) 
    {}

    #[Route('/', name: 'app_home')]
    public function index(SlidersRepository $slider): Response
    {
        $sliders = $slider->findAll();

        return $this->render('home/index.html.twig', [
            'sliders' => $sliders,
            'productBestSeller' => $this->product->findBy(['isBestSeller' => true], ['id' => 'DESC']),
            'productNewArrival' => $this->product->findBy(['isNewArrival' => true], ['id' => 'DESC']),
            'productAll' => $this->product->findBy(['isAvailable' => true], ['id' => 'DESC']),
            // 'productSpecialOffer' => $this->product->findBy(['isSpecialOffer' => true]),
            'seo' => [
                'title' => 'Miels rares du monde | Nidemiel',
                'description' => 'Sélection de miels premium : origine, rareté, pureté et goût d’exception.',
                'canonical' => $this->generateUrl('app_home', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
                'robots' => 'index,follow',
                'og' => [
                    'title' => 'Miels rares du monde | Nidemiel',
                    'description' => 'Sélection de miels premium : origine, rareté, pureté et goût d’exception.',
                    'url' => $this->generateUrl('app_home', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL),
                    'type' => 'website',
                    'image' => null,
                ],
                'jsonLd' => [],
            ],
        ]);
    }
}
