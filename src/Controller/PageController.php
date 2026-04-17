<?php

namespace App\Controller;

use App\Repository\PageRepository;
use App\Seo\SeoResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Contrôleur public pour l'affichage des pages statiques du site (mentions légales, CGV, etc.) identifiées par leur slug.
 */
final class PageController extends AbstractController
{
    #[Route('/page/{slug}', name: 'app_page')]
    public function index(string $slug, PageRepository $pageRepo, SeoResolver $seoResolver, Request $request): Response
    {
        $page = $pageRepo->findOneBy(['slug' => $slug]);

        if (!$page) {
            throw $this->createNotFoundException();
        }

        $canonical = $this->generateUrl('app_page', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL);

        $seo = $seoResolver->forStaticPage([
            'title' => ($page->getSeoTitle() ?: $page->getTitle()) . ' | Nidemiel',
            'description' => $page->getSeoDescription() ?: $page->getTitle(),
            'canonical' => $canonical,
            'robots' => $page->isSeoNoindex() ? 'noindex,follow' : 'index,follow',
            'ogType' => 'website',
            'breadcrumbs' => [
                ['name' => 'Accueil', 'url' => $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL)],
                ['name' => $page->getTitle(), 'url' => $canonical],
            ],
        ], $request);

        return $this->render('page/index.html.twig', [
            'page' => $page,
            'seo' => $seo,
        ]);
    }
}
