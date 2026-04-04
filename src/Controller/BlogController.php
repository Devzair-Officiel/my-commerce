<?php

namespace App\Controller;

use App\Repository\BlogRepository;
use App\Seo\SeoResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlogController extends AbstractController
{
    public function __construct(private BlogRepository $blogRepo) {}
    
    #[Route('/blog', name: 'app_blog')]
    public function index(SeoResolver $seoResolver, Request $request): Response
    {
        $blogs = $this->blogRepo->findBy(['isPublished' => true]);

        $seo = $seoResolver->forStaticPage([
            'title' => 'Blog miel naturel – Conseils, recettes & apiculture | Nidemiel',
            'description' => 'Découvrez nos articles sur le miel naturel : bienfaits, recettes, conseils d\'utilisation et actualités de l\'apiculture.',
            'route' => 'app_blog',
            'robots' => 'index,follow',
            'ogType' => 'website',
            'breadcrumbs' => [
                ['name' => 'Accueil', 'url' => $this->generateUrl('app_home', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL)],
                ['name' => 'Blog', 'url' => $this->generateUrl('app_blog', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL)],
            ],
        ], $request);

        return $this->render('blog/index.html.twig', [
            'blogs' => $blogs,
            'seo' => $seo,
        ]);
    }

    #[Route(path: '/blog/{slug}', name: 'app_blog_show')]
    public function showArticle(string $slug, SeoResolver $seoResolver, Request $request): Response
    {

        $blog = $this->blogRepo->findOneBy(['slug' => $slug]);

        $seo = $seoResolver->forBlog($blog, $request);

        return $this->render('blog/show.html.twig', [
            'blog' => $blog,
            'seo' => $seo,
        ]);
    }
}
