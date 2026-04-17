<?php

namespace App\Service;

use App\Entity\Setting;
use App\Entity\User;
use App\Repository\CarrierRepository;
use App\Repository\ProductRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Gère le panier côté client via la session (ajout/retrait d’articles, transporteur, totaux).
 * Ne réserve ni ne décrémente le stock : le stock est décrémenté uniquement au paiement confirmé (webhook).
 * Calcule les montants TTC/HT/TVA en centimes, de façon déterministe, à partir du taux TVA stocké en Setting.
 */
final class CartService
{
    private const SESSION_CART         = 'cart';
    private const SESSION_CARRIER      = 'carrier';
    private const SESSION_PICKUP_POINT = 'pickup_point';

    private ?Setting $cachedSetting = null;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly SettingRepository $settingRepo,
        private readonly ProductRepository $productRepo,
        private readonly CarrierRepository $carrierRepo,
        private readonly Security $security,
        private readonly EntityManagerInterface $em,
        private readonly int $freeShippingThresholdCents, // injecté via services.yaml
    ) {}

    private function session(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            throw new \LogicException('CartService utilisé hors requête HTTP (pas de session disponible).');
        }

        return $request->getSession();
    }

    /**
     * @return array<int, int> [productId => quantity]
     */
    private function getCartRaw(): array
    {
        $cart = $this->session()->get(self::SESSION_CART, []);
        if (!\is_array($cart)) {
            return [];
        }

        return $this->normalizeCart($cart);
    }

    /**
     * @param array<mixed, mixed> $cart
     * @return array<int, int>
     */
    private function normalizeCart(array $cart): array
    {
        $normalized = [];

        foreach ($cart as $productId => $qty) {
            $id = (int) $productId;
            $quantity = (int) $qty;

            if ($id <= 0 || $quantity <= 0) {
                continue;
            }

            $normalized[$id] = ($normalized[$id] ?? 0) + $quantity;
        }

        return $normalized;
    }

    /**
     * @param array<int, int> $cart
     */
    private function saveCart(array $cart): void
    {
        $this->session()->set(self::SESSION_CART, $cart);
        $this->persistCartForCurrentUser($cart ?: null);
    }

    public function get(string $key): mixed
    {
        return $this->session()->get($key, []);
    }

    public function update(string $key, mixed $value): void
    {
        $this->session()->set($key, $value);
    }

    public function getRawCart(): array
    {
        return $this->getCartRaw();
    }

    public function setRawCart(array $cart): void
    {
        $this->session()->set(self::SESSION_CART, $cart);
    }

    public function clearCart(): void
    {
        $session = $this->session();

        $session->remove(self::SESSION_CART);
        $session->remove(self::SESSION_CARRIER);
        $session->remove(self::SESSION_PICKUP_POINT);
        $session->save();

        $this->persistCartForCurrentUser(null);
    }

    /**
     * Persiste le panier en BDD pour l'utilisateur connecté.
     * Utilise une référence Doctrine (sans SELECT) pour éviter une requête inutile.
     */
    private function persistCartForCurrentUser(?array $cart): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || $user->getId() === null) {
            return;
        }

        /** @var User $managedUser */
        $managedUser = $this->em->getReference(User::class, $user->getId());
        $managedUser->setSavedCart($cart);
        $this->em->flush();
    }

    public function updateCarrier(array $carrier): void
    {
        $this->update(self::SESSION_CARRIER, $carrier);
    }

    /**
     * Stocke le point relais choisi (Mondial Relay) en session.
     *
     * @param array{id: string, name: string, address: string, city: string, postalCode: string} $point
     */
    public function setPickupPoint(array $point): void
    {
        $this->session()->set(self::SESSION_PICKUP_POINT, $point);
    }

    public function clearPickupPoint(): void
    {
        $this->session()->remove(self::SESSION_PICKUP_POINT);
    }

    /**
     * @return array{id: string, name: string, address: string, city: string, postalCode: string}|null
     */
    public function getPickupPoint(): ?array
    {
        $data = $this->session()->get(self::SESSION_PICKUP_POINT);
        return is_array($data) ? $data : null;
    }

    /**
     * Ajoute au panier sans toucher le stock.
     * On peut refuser si stock affiché insuffisant, mais la vérité est revalidée à la confirmation/paiement.
     */
    public function addToCart(int $productId, int $count = 1): void
    {
        if ($productId <= 0) {
            throw new \InvalidArgumentException('Produit invalide.');
        }
        if ($count <= 0) {
            throw new \InvalidArgumentException('Quantité invalide.');
        }

        $product = $this->productRepo->find($productId);
        if (!$product) {
            throw new \RuntimeException('Produit non trouvé.');
        }

        if ($product->isAvailable() === false) {
            throw new \RuntimeException('Ce produit n\'est pas disponible.');
        }

        $cart = $this->getCartRaw();
        $currentQty = (int) ($cart[$productId] ?? 0);
        $newQty = $currentQty + $count;

        // null = stock non géré (ex: produit numérique) → pas de limite
        // 0   = épuisé → on bloque
        $stock = $product->getStock();
        if ($stock !== null && $newQty > $stock) {
            throw new \RuntimeException('Stock insuffisant pour le produit demandé.');
        }

        $cart[$productId] = $newQty;
        $this->saveCart($cart);
    }


    /**
     * Retire du panier sans toucher le stock.
     */
    public function removeToCart(int $productId, int $count = 1): void
    {
        if ($productId <= 0) {
            return;
        }
        if ($count <= 0) {
            throw new \InvalidArgumentException('Quantité invalide.');
        }

        $cart = $this->getCartRaw();
        if (!isset($cart[$productId])) {
            return;
        }

        $currentQty = (int) $cart[$productId];
        $toRemove = min($currentQty, $count);

        $remaining = $currentQty - $toRemove;
        if ($remaining <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $remaining;
        }

        $this->saveCart($cart);
    }

    /**
     * @return array{
     *   items: array<int, array{
     *     product: array{
     *       id:int,
     *       title:mixed,
     *       description:mixed,
     *       slug:mixed,
     *       image:mixed,
     *       stock:mixed,
     *       soldePrice:mixed,
     *       regularPrice:mixed,
     *       displayPrice:int
     *     },
     *     weight_grams: int,
     *     line_weight_grams: int,
     *     quantity:int,
     *     sub_total_ht:int,
     *     taxe:int,
     *     sub_total:int
     *   }>,
     *   sub_total:int,
     *   sub_total_ht:int,
     *   taxe:int,
     *   cart_count:int,
     *   quantity:int,
     *   carrier: array{id:mixed,name:string,description:string,price:int},
     *   sub_total_with_carrier:int
     * }
     */
    public function getCartDetails(): array
    {
        $cart = $this->getCartRaw();

        $result = [
            'items' => [],
            'sub_total' => 0,
            'sub_total_ht' => 0,
            'taxe' => 0,
            'cart_count' => 0,
            'total_weight_grams' => 0,
            'quantity' => 0,
        ];

        $carrier = $this->resolveCarrierFromSessionOrDefault();

        if ($cart === []) {
            $carrier = $this->applyFreeShippingIfEligible($carrier, 0);

            $result['carrier'] = $carrier;
            $result['sub_total_with_carrier'] = 0 + (int) ($carrier['price'] ?? 0);

            $this->saveCart([]);

            return $result;
        }

        $ratePercent = $this->getTaxRatePercent();

        // 1 requête pour tous les produits du panier
        $ids = array_keys($cart);
        $products = $this->productRepo->findBy(['id' => $ids]);

        $productsById = [];
        foreach ($products as $p) {
            $productsById[(int) $p->getId()] = $p;
        }

        $subTotal = 0;

        foreach ($cart as $productId => $quantity) {
            $product = $productsById[$productId] ?? null;
            if (!$product) {
                unset($cart[$productId]);
                continue;
            }

            $qty = (int) $quantity;
            if ($qty <= 0) {
                unset($cart[$productId]);
                continue;
            }

            $unitWeight = max(0, (int) ($product->getWeightGrams() ?? 0));
            $lineWeight = $unitWeight * $qty;

            $result['total_weight_grams'] += $lineWeight;

            $unitPrice = $product->isOnSale() && $product->getSoldePrice() !== null
                ? (int) $product->getSoldePrice()
                : (int) $product->getRegularPrice();

            $lineTotal = $unitPrice * $qty;
            $subTotal += $lineTotal;

            $split = $this->splitTtcIntoHtAndTax($lineTotal, $ratePercent);

            $result['items'][] = [
                'product' => [
                    'id' => (int) $product->getId(),
                    'title' => $product->getTitle(),
                    'description' => $product->getDescription(),
                    'slug' => $product->getSlug(),
                    'image' => $product->getMediaData(),
                    'stock' => $product->getStock(),
                    'soldePrice' => $product->getSoldePrice(),
                    'regularPrice' => $product->getRegularPrice(),
                    'displayPrice' => $unitPrice,
                ],
                'quantity' => $qty,
                'weight_grams' => $unitWeight,
                'line_weight_grams' => $lineWeight,
                'sub_total_ht' => $split['ht'],
                'taxe' => $split['tax'],
                'sub_total' => $lineTotal,
            ];

            $result['cart_count'] += $qty;
            $result['quantity'] += $qty;
        }

        // Nettoyage session si produits invalides/supprimés
        $this->saveCart($cart);

        $result['sub_total'] = $subTotal;

        $totalSplit = $this->splitTtcIntoHtAndTax($subTotal, $ratePercent);
        $result['sub_total_ht'] = $totalSplit['ht'];
        $result['taxe'] = $totalSplit['tax'];

        $carrier = $this->applyFreeShippingIfEligible($carrier, $result['sub_total']);

        $result['carrier'] = $carrier;
        $result['sub_total_with_carrier'] = $result['sub_total'] + (int) ($carrier['price'] ?? 0);

        $threshold = $this->freeShippingThresholdCents;

        $result['free_shipping_threshold'] = $threshold;
        $result['free_shipping_remaining'] = max(
            0,
            $threshold - $result['sub_total']
        );

        return $result;
    }

    public function isStockSufficient(int $productId, int $quantity): bool
    {
        if ($productId <= 0 || $quantity <= 0) {
            return false;
        }

        $product = $this->productRepo->find($productId);
        if (!$product) {
            return false;
        }

        return (int) $product->getStock() >= $quantity;
    }

    private function resolveCarrierFromSessionOrDefault(): array
    {
        $carrier = $this->get(self::SESSION_CARRIER);

        if (\is_array($carrier) && isset($carrier['price'])) {
            // Normalise pour garantir int
            $carrier['price'] = (int) $carrier['price'];
            return $carrier;
        }

        $defaultCarrierEntity = $this->carrierRepo->findOneBy([], ['id' => 'ASC']);

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
                'name' => (string) $defaultCarrierEntity->getName(),
                'description' => (string) $defaultCarrierEntity->getDescription(),
                'price' => (int) $defaultCarrierEntity->getPrice(),
            ];
        }

        $this->update(self::SESSION_CARRIER, $carrier);

        return $carrier;
    }

    /**
     * Applique la livraison offerte si le total TTC des produits atteint le seuil.
     *
     * @param array{name?:string, price?:int} $carrier
     */
    private function applyFreeShippingIfEligible(array $carrier, int $itemsTotalTtcCents): array
    {
        $eligible = $itemsTotalTtcCents >= $this->freeShippingThresholdCents;

        // flag exploitable en Twig/JS
        $carrier['free_shipping'] = $eligible;

        if ($eligible) {
            // IMPORTANT: on force à 0 pour que le calcul du total reste correct côté serveur
            $carrier['price'] = 0;
        }

        return $carrier;
    }

    private function getTaxRatePercent(): int
    {
        // Utilise le repo "layout-safe" (scalaires) pour éviter de hydrater l’entité si tu préfères,
        // mais ici on privilégie une lecture unique + cohérente (tax + seuil).
        $rate = (int) ($this->setting()->getTaxeRate() ?? 0);

        if ($rate < 0 || $rate > 100) {
            throw new \RuntimeException('taxe_rate invalide dans Setting.');
        }

        return $rate;
    }

    private function setting(): Setting
    {
        if ($this->cachedSetting) {
            return $this->cachedSetting;
        }

        /** @var Setting|null $setting */
        $setting = $this->settingRepo->findOneBy([], ['id' => 'ASC']);
        if (!$setting) {
            throw new \RuntimeException('Setting manquant en base.');
        }

        return $this->cachedSetting = $setting;
    }

    /**
     * Découpe un montant TTC (centimes) en HT + TVA (centimes) sans float.
     *
     * @return array{ht:int,tax:int}
     */
    private function splitTtcIntoHtAndTax(int $ttcCents, int $ratePercent): array
    {
        if ($ttcCents < 0) {
            throw new \InvalidArgumentException('Montant TTC invalide.');
        }
        if ($ratePercent < 0 || $ratePercent > 100) {
            throw new \InvalidArgumentException('Taux TVA invalide.');
        }

        if ($ratePercent === 0 || $ttcCents === 0) {
            return ['ht' => $ttcCents, 'tax' => 0];
        }

        $den = 100 + $ratePercent;

        // half-up, 100% integer
        $ht = intdiv($ttcCents * 100 + intdiv($den, 2), $den);
        $tax = $ttcCents - $ht;

        return ['ht' => $ht, 'tax' => $tax];
    }
}
