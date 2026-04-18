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
    public function __construct(private readonly string $brandName) {}

    #[Route('/page/{slug}', name: 'app_page')]
    public function index(string $slug, PageRepository $pageRepo, SeoResolver $seoResolver, Request $request): Response
    {
        $page = $pageRepo->findOneBy(['slug' => $slug]);

        if (!$page) {
            throw $this->createNotFoundException();
        }

        $canonical = $this->generateUrl('app_page', ['slug' => $slug], UrlGeneratorInterface::ABSOLUTE_URL);

        $faq = $this->extractFaqFromHtml($page->getContent() ?? '');

        $seoData = [
            'title' => ($page->getSeoTitle() ?: $page->getTitle()) . ' | ' . $this->brandName,
            'description' => $page->getSeoDescription() ?: $page->getTitle(),
            'canonical' => $canonical,
            'robots' => $page->isSeoNoindex() ? 'noindex,follow' : 'index,follow',
            'ogType' => 'website',
            'breadcrumbs' => [
                ['name' => 'Accueil', 'url' => $this->generateUrl('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL)],
                ['name' => $page->getTitle(), 'url' => $canonical],
            ],
        ];

        if ($faq !== []) {
            $seoData['faq'] = $faq;
        }

        $seo = $seoResolver->forStaticPage($seoData, $request);

        return $this->render('page/index.html.twig', [
            'page' => $page,
            'seo' => $seo,
        ]);
    }

    /**
     * Extrait des paires question/réponse depuis le HTML d'une page.
     * Cherche les balises h2/h3 suivies d'un paragraphe.
     * Retourne au moins 2 paires pour justifier un FAQPage JSON-LD.
     *
     * @return array<int, array{question: string, answer: string}>
     */
    private function extractFaqFromHtml(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="UTF-8">' . $html, \LIBXML_HTML_NOIMPLIED | \LIBXML_HTML_NODEFDTD);

        $faq = [];
        $xpath = new \DOMXPath($doc);
        $headings = $xpath->query('//h2|//h3');

        if ($headings === false) {
            return [];
        }

        foreach ($headings as $heading) {
            $question = trim($heading->textContent);
            if ($question === '') {
                continue;
            }

            // Cherche le premier <p> frère suivant
            $sibling = $heading->nextSibling;
            while ($sibling !== null && !($sibling instanceof \DOMElement && $sibling->tagName === 'p')) {
                $sibling = $sibling->nextSibling;
            }

            if ($sibling instanceof \DOMElement) {
                $answer = trim($sibling->textContent);
                if ($answer !== '') {
                    $faq[] = ['question' => $question, 'answer' => $answer];
                }
            }
        }

        // Ne génère un FAQPage que si au moins 2 paires sont trouvées
        return \count($faq) >= 2 ? $faq : [];
    }
}
