<?php

declare(strict_types=1);

namespace App\Controller;

use App\Seo\SeoResolver;
use App\Service\GuideSensorielDataProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Page publique du Guide Sensoriel Nidemiel.
 * Contenu éditorial statique servi via GuideSensorielDataProvider.
 */
final class GuideSensorielController extends AbstractController
{
    public function __construct(
        private readonly GuideSensorielDataProvider $data,
    ) {}

    #[Route('/guide-sensoriel-miel', name: 'app_guide_sensoriel', methods: ['GET'])]
    #[Cache(smaxage: 3600, vary: ['Accept-Language'])]
    public function index(Request $request, SeoResolver $seoResolver, UrlGeneratorInterface $urlGenerator): Response
    {
        $faqs = $this->data->getFaqs();

        $homeUrl  = $urlGenerator->generate('app_home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $guideUrl = $urlGenerator->generate('app_guide_sensoriel', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $seo = $seoResolver->forStaticPage([
            'title'       => 'Guide Sensoriel Nidemiel — Déguster, comprendre et choisir un miel',
            'description' => 'Apprenez à déguster un miel : textures, couleurs, familles aromatiques, roue des arômes et profils des 10 miels Nidemiel. Un guide clair, sensoriel et gourmand.',
            'route'       => 'app_guide_sensoriel',
            'breadcrumbs' => [
                ['name' => 'Accueil', 'url' => $homeUrl],
                ['name' => 'Le Guide Sensoriel Nidemiel', 'url' => $guideUrl],
            ],
            'faq' => array_map(
                static fn(array $f): array => ['question' => $f['question'], 'answer' => $f['answer']],
                $faqs,
            ),
        ], $request);

        return $this->render('guide/sensoriel.html.twig', [
            'seo'        => $seo,
            'toc'        => $this->data->getToc(),
            'learn'      => $this->data->getLearn(),
            'steps'      => $this->data->getSteps(),
            'erreurs'    => $this->data->getErreurs(),
            'criteres'   => $this->data->getCriteres(),
            'textures'   => $this->data->getTextures(),
            'couleurs'   => $this->data->getCouleurs(),
            'familles'   => $this->data->getFamilles(),
            'sensations' => $this->data->getSensations(),
            'accords'    => $this->data->getAccords(),
            'miels'      => $this->data->getMiels(),
            'faqs'       => $faqs,
        ]);
    }
}
