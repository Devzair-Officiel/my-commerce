<?php

namespace App\Controller;

use App\Service\CompareService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class CompareController extends AbstractController
{
    public function __construct(private CompareService $compareService)
    {}

    #[Route('/compare', name: 'app_compare')]
    public function index(): Response
    {
        $compare = $this->compareService->getCompareDetails();

        return $this->render('compare/index.html.twig', [
            'compare' => $compare,
        ]);
    }

    #[Route('/compare/add/{productId}', name: 'app_add_to_compare')]
    public function addToCompare(int $productId): Response
    {
        $this->compareService->addToCompare($productId);
        $compare = $this->compareService->getCompareDetails();

        // return $this->redirectToRoute('app_compare');
        return $this->json($compare);
    }

    #[Route('/compare/remove/{productId}', name: 'app_remove_compare')]
    public function reomoveToCompare(int $productId): Response
    {
        $this->compareService->removeToCompare($productId);
        $compare = $this->compareService->getCompareDetails();
        return $this->json($compare);
    }

    #[Route('/compare/get', name: 'app_get_compare')]
    public function getCompare(): Response
    {
        $compare = $this->compareService->getCompareDetails();
        return $this->json($compare);
    }
}
