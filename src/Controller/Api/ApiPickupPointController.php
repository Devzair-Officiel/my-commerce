<?php

namespace App\Controller\Api;

use App\Service\Carrier\MondialRelayClient;
use App\Service\CartService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ApiPickupPointController
{
    public function __construct(
        private readonly MondialRelayClient $mondialRelayClient,
        private readonly CartService $cartService,
    ) {}

    /**
     * Recherche les points relais Mondial Relay proches d'un code postal.
     *
     * GET /api/pickup-points?postal_code=75001
     */
    #[Route('/api/pickup-points', name: 'api_pickup_points_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $postalCode = trim((string) $request->query->get('postal_code', ''));

        if (!preg_match('/^\d{5}$/', $postalCode)) {
            return new JsonResponse(['error' => 'Code postal invalide (5 chiffres requis).'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $points = $this->mondialRelayClient->findPickupPoints($postalCode);
        } catch (\RuntimeException $e) {
            return new JsonResponse(['error' => 'Service temporairement indisponible.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return new JsonResponse($points);
    }

    /**
     * Enregistre le point relais choisi dans la session panier.
     *
     * POST /api/pickup-points/select
     * Body JSON : { "id": "004100", "name": "...", "address": "...", "city": "...", "postalCode": "75001" }
     */
    #[Route('/api/pickup-points/select', name: 'api_pickup_point_select', methods: ['POST'])]
    public function select(Request $request): JsonResponse
    {
        $data = json_decode((string) $request->getContent(), true);

        if (!is_array($data) || empty($data['id'])) {
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
     * Efface le point relais de la session (si l'utilisateur change de transporteur).
     *
     * DELETE /api/pickup-points/select
     */
    #[Route('/api/pickup-points/select', name: 'api_pickup_point_clear', methods: ['DELETE'])]
    public function clear(): JsonResponse
    {
        $this->cartService->clearPickupPoint();

        return new JsonResponse(['ok' => true]);
    }
}
