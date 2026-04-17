<?php

namespace App\Controller\Api;

use App\Service\Carrier\ColissimoClient;
use App\Service\CartService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API JSON pour la sélection d'un point relais Colissimo lors du tunnel de commande.
 * Enregistre le point relais choisi dans la session panier de l'utilisateur connecté.
 */
#[IsGranted('ROLE_USER')]
final class ApiPickupPointController
{
    public function __construct(
        private readonly ColissimoClient $colissimoClient,
        private readonly CartService $cartService,
    ) {}

    /**
     * Enregistre le point relais choisi dans la session panier.
     */
    #[Route('/api/pickup-points/select', name: 'api_pickup_point_select', methods: ['POST'])]
    public function select(Request $request): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);

        if (!\is_array($data) || empty($data['id'])) {
            return new JsonResponse(['error' => 'Données invalides.'], Response::HTTP_BAD_REQUEST);
        }

        $this->cartService->setPickupPoint([
            'id'         => (string) $data['id'],
            'name'       => (string) ($data['name'] ?? ''),
            'address'    => (string) ($data['address'] ?? ''),
            'city'       => (string) ($data['city'] ?? ''),
            'postalCode' => (string) ($data['postalCode'] ?? ''),
        ]);

        return new JsonResponse(['ok' => true]);
    }

    /**
     * Efface le point relais de la session.
     */
    #[Route('/api/pickup-points/select', name: 'api_pickup_point_clear', methods: ['DELETE'])]
    public function clear(): JsonResponse
    {
        $this->cartService->clearPickupPoint();

        return new JsonResponse(['ok' => true]);
    }
}
