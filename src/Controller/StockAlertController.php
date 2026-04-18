<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\StockAlert;
use App\Repository\ProductRepository;
use App\Repository\StockAlertRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email as EmailConstraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class StockAlertController extends AbstractController
{
    #[Route('/stock-alert/subscribe/{id<\d+>}', name: 'app_stock_alert_subscribe', methods: ['POST'])]
    public function subscribe(
        int $id,
        Request $request,
        ProductRepository $productRepository,
        StockAlertRepository $stockAlertRepository,
        CartService $cartService,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
    ): JsonResponse {
        $product = $productRepository->find($id);

        if (!$product) {
            return $this->json(['error' => 'Produit introuvable.'], 404);
        }

        // Vérifie le stock disponible en tenant compte du panier de l'utilisateur
        $cartRaw        = $cartService->getRawCart();
        $qtyInCart      = (int) ($cartRaw[$product->getId()] ?? 0);
        $rawStock       = $product->getStock();
        $availableStock = $rawStock === null ? null : max(0, $rawStock - $qtyInCart);

        if ($availableStock === null || $availableStock > 0) {
            return $this->json(['error' => 'Ce produit est déjà disponible.'], 400);
        }

        // Récupère l'email : user connecté ou champ du formulaire
        $user = $this->getUser();
        $email = $user?->getUserIdentifier() ?? trim((string) $request->request->get('email', ''));

        if ($email === '') {
            return $this->json(['error' => 'Adresse e-mail requise.'], 400);
        }

        $errors = $validator->validate($email, new EmailConstraint());
        if (count($errors) > 0) {
            return $this->json(['error' => 'Adresse e-mail invalide.'], 400);
        }

        // Déjà inscrit
        if ($stockAlertRepository->findByEmailAndProduct($email, $product)) {
            return $this->json(['message' => 'Vous êtes déjà inscrit pour ce produit.']);
        }

        $alert = new StockAlert($email, $product);
        $em->persist($alert);
        $em->flush();

        return $this->json(['message' => 'Vous serez alerté dès le retour en stock.']);
    }

    #[Route('/stock-alert/unsubscribe/{id<\d+>}', name: 'app_stock_alert_unsubscribe', methods: ['POST'])]
    public function unsubscribe(
        int $id,
        Request $request,
        ProductRepository $productRepository,
        StockAlertRepository $stockAlertRepository,
        EntityManagerInterface $em,
    ): JsonResponse {
        $product = $productRepository->find($id);

        if (!$product) {
            return $this->json(['error' => 'Produit introuvable.'], 404);
        }

        $user  = $this->getUser();
        $email = $user?->getUserIdentifier() ?? trim((string) $request->request->get('email', ''));

        if ($email === '') {
            return $this->json(['error' => 'Adresse e-mail requise.'], 400);
        }

        $alert = $stockAlertRepository->findByEmailAndProduct($email, $product);

        if (!$alert) {
            return $this->json(['error' => 'Inscription introuvable.'], 404);
        }

        $em->remove($alert);
        $em->flush();

        return $this->json(['message' => 'Alerte annulée.']);
    }
}
