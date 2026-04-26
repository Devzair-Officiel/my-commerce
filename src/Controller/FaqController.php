<?php

namespace App\Controller;

use App\Repository\FaqLinkRepository;
use App\Repository\FaqRepository;
use App\Seo\SeoResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Contrôleur de la page des question : affiche les sliders toutes les questions frequente
 */
final class FaqController extends AbstractController
{
    #[Route('/faq-miel', name: 'app_faq')]
    #[Cache(smaxage: 3600, vary: ['Accept-Language'])]
    public function index(FaqRepository $faqRepository, FaqLinkRepository $faqLinkRepository, SeoResolver $seoResolver, Request $request): Response
    {
        $faqsBySection = $faqRepository->findActiveGroupedBySection();
        $faqs = $faqRepository->findActive();
        $seo  = $seoResolver->forFaq($faqs, $request);

        return $this->render('faq/index.html.twig', [
            'faqsBySection' => $faqsBySection,
            'faqs'          => $faqs,
            'faqLinks'      => $faqLinkRepository->findActive(),
            'seo'           => $seo,
        ]);
    }
}
