<?php

namespace App\Controller;

use App\Repository\BlogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BlogController extends AbstractController
{
    public function __construct(private BlogRepository $blogRepo) {}
    
    #[Route('/blog', name: 'app_blog')]
    public function index(): Response
    {
        $blogs = $this->blogRepo->findBy(['isPublished' => true]);

        return $this->render('blog/index.html.twig', [
            'blogs' => $blogs,
        ]);
    }

    #[Route(path: '/blog/{slug}', name: 'app_show_blog')]
    public function showArticle(string $slug): Response
    {

        $blog = $this->blogRepo->findOneBy(['slug' => $slug]);

        return $this->render('blog/show.html.twig', [
            'blog' => $blog

        ]);
    }
}
