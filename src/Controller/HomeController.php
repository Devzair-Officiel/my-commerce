<?php

namespace App\Controller;

use App\Repository\PageRepository;
use App\Repository\ProductRepository;
use App\Repository\SettingRepository;
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
    public function index(SettingRepository $setting, SlidersRepository $slider, PageRepository $page, Request $request): Response
    {
        $session = $request->getSession();
        $setting = $setting->findAll();
        $sliders = $slider->findAll();

        $session->set('setting', $setting[0]);

        $headerPages = $page->findBy(['isHead' => true]);
        $footerPages = $page->findBy(['isFoot' => true]);

        $session->set("headerPages", $headerPages);
        $session->set("footerPages", $footerPages);

        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'sliders' => $sliders,
            'productBestSeller' => $this->product->findBy(['isBestSeller' => true]),
            'productNewArrival' => $this->product->findBy(['isNewArrival' => true]),
            'productFeatured' => $this->product->findBy(['isFeatured' => true]),
            'productSpecialOffer' => $this->product->findBy(['isSpecialOffer' => true]),
        ]);
    }

}
