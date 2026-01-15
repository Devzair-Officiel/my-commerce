<?php

namespace App\Controller;

use App\Repository\CategoryRepository;
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
    public function index(SlidersRepository $slider): Response
    {
        $sliders = $slider->findAll();

        return $this->render('home/index.html.twig', [
            'sliders' => $sliders,
            'productBestSeller' => $this->product->findBy(['isBestSeller' => true]),
            'productNewArrival' => $this->product->findBy(['isNewArrival' => true]),
            'productFeatured' => $this->product->findBy(['isFeatured' => true]),
            'productSpecialOffer' => $this->product->findBy(['isSpecialOffer' => true]),
        ]);
    }

    #[Route('/product/{slug}', name: 'app_product_by_slug')]
    public function showProduct(string $slug)
    {
        $product = $this->product->findOneBy(['slug' => $slug]);

        if (!$product) {
            throw $this->createNotFoundException();
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

}
