<?php

namespace App\Service;

use App\Repository\CarrierRepository;
use App\Repository\ProductRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CartService
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $em,
        private readonly SettingRepository $settingRepo,
        private readonly ProductRepository $productRepo,
        private readonly CarrierRepository $carrierRepo,
    ) {}

    private function session(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new \LogicException('CartService utilisé hors requête HTTP (pas de session disponible).');
        }

        return $request->getSession();
    }

    public function get(string $key): mixed
    {
        return $this->session()->get($key, []);
    }

    public function update(string $key, mixed $value): void
    {
        $this->session()->set($key, $value);
    }

    public function clearCart(): void
    {
        $this->update('cart', []);
    }

    public function updateCarrier(array $carrier): void
    {
        $this->update('carrier', $carrier);
    }

    /**
     * ⚠️ ATTENTION :
     * Décrémenter le stock à l'ajout au panier n'est pas robuste (abandon de panier).
     * Le stock devrait être décrémenté au paiement confirmé (webhook).
     * Je garde ton comportement actuel, mais à corriger ensuite.
     */
    public function addToCart(int $productId, int $count = 1): void
    {
        if ($count <= 0) {
            throw new \InvalidArgumentException('Quantité invalide.');
        }

        $product = $this->productRepo->find($productId);
        if (!$product) {
            throw new \RuntimeException('Produit non trouvé.');
        }

        $availableStock = (int) $product->getStock();
        if ($availableStock < $count) {
            throw new \RuntimeException('Stock insuffisant pour le produit demandé.');
        }

        $product->setStock($availableStock - $count);
        $this->em->flush();

        $cart = $this->get('cart');
        if (!\is_array($cart)) {
            $cart = [];
        }

        $cart[$productId] = ($cart[$productId] ?? 0) + $count;
        $this->update('cart', $cart);
    }

    public function removeToCart(int $productId, int $count = 1): void
    {
        if ($count <= 0) {
            throw new \InvalidArgumentException('Quantité invalide.');
        }

        $cart = $this->get('cart');
        if (!\is_array($cart) || !isset($cart[$productId])) {
            return;
        }

        $product = $this->productRepo->find($productId);
        if (!$product) {
            // Produit supprimé : on retire juste de la session
            unset($cart[$productId]);
            $this->update('cart', $cart);
            return;
        }

        $currentQty = (int) $cart[$productId];
        $toRemove = min($currentQty, $count);

        $product->setStock(((int) $product->getStock()) + $toRemove);
        $this->em->flush();

        $remaining = $currentQty - $toRemove;
        if ($remaining <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $remaining;
        }

        $this->update('cart', $cart);
    }

    public function getCartDetails(): array
    {
        $cart = $this->get('cart');
        if (!\is_array($cart)) {
            $cart = [];
        }

        $result = [
            'items' => [],
            'sub_total' => 0,
            'sub_total_ht' => 0,
            'taxe' => 0,
            'cart_count' => 0,
            'quantity' => 0,
        ];

        $subTotal = 0;
        $taxeRate = 0; // TODO: config TVA

        foreach ($cart as $productId => $quantity) {
            $quantity = (int) $quantity;
            if ($quantity <= 0) {
                unset($cart[$productId]);
                continue;
            }

            $product = $this->productRepo->find((int) $productId);
            if (!$product) {
                unset($cart[$productId]);
                continue;
            }

            $unitPrice = (int) $product->getSoldePrice();
            $currentSubTotal = $unitPrice * $quantity;

            $subTotal += $currentSubTotal;

            $result['items'][] = [
                'product' => [
                    'id' => $product->getId(),
                    'title' => $product->getTitle(),
                    'description' => $product->getDescription(),
                    'slug' => $product->getSlug(),
                    'image' => $product->getMediaFilenames(),
                    'stock' => $product->getStock(),
                    'soldePrice' => $product->getSoldePrice(),
                    'regularPrice' => $product->getRegularPrice(),
                ],
                'quantity' => $quantity,
                'sub_total_ht' => (int) round($currentSubTotal / (1 + $taxeRate)),
                'taxe' => (int) round($taxeRate * $currentSubTotal / (1 + $taxeRate)),
                'sub_total' => $currentSubTotal,
            ];

            $result['cart_count'] += $quantity;
            $result['quantity'] += $quantity;
        }

        // Nettoyage panier si produits invalides
        $this->update('cart', $cart);

        $result['sub_total'] = $subTotal;
        $result['sub_total_ht'] = (int) round($subTotal / (1 + $taxeRate));
        $result['taxe'] = (int) round($taxeRate * $result['sub_total_ht']);

        // Carrier en session ou default
        $carrier = $this->get('carrier');

        if (!\is_array($carrier) || !isset($carrier['price'])) {
            $defaultCarrierEntity = $this->carrierRepo->findOneBy([]);

            if (!$defaultCarrierEntity) {
                $carrier = [
                    'id' => null,
                    'name' => 'Livraison',
                    'description' => '',
                    'price' => 0,
                ];
            } else {
                $carrier = [
                    'id' => $defaultCarrierEntity->getId(),
                    'name' => $defaultCarrierEntity->getName(),
                    'description' => $defaultCarrierEntity->getDescription(),
                    'price' => $defaultCarrierEntity->getPrice(),
                ];
            }

            $this->update('carrier', $carrier);
        }

        // Livraison offerte au delà d'un seuil
        if ($result['sub_total'] > 5900) {
            $carrier['price'] = 0;
        }

        $result['carrier'] = $carrier;
        $result['sub_total_with_carrier'] = $result['sub_total'] + (int) ($carrier['price'] ?? 0);

        return $result;
    }

    public function isStockSufficient(int $productId, int $quantity): bool
    {
        $product = $this->productRepo->find($productId);
        if (!$product) {
            return false;
        }

        return (int) $product->getStock() >= $quantity;
    }
}
