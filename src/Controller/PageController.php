<?php

namespace App\Controller;

use App\Repository\PageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    #[Route('/page/{slug}', name: 'app_page')]
    public function index(string $slug, PageRepository $page): Response
    {
        $page = $page->findOneBy(["slug" => $slug]);

        if (!$page) {
            return $this->render('page/not-found.html.twig');
        }

        return $this->render('page/index.html.twig', [
            'controller_name' => 'PageController',
            'page' => $page,
        ]);
    }
}
