<?php

namespace App\Service;

use App\Repository\CarrierRepository;
use App\Repository\ProductRepository;
use App\Repository\SettingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class CartService
{
    private $em;
    public function __construct(
        private RequestStack $requestStack, 
        EntityManagerInterface $em, 
        private SettingRepository $settingRepo,
        private ProductRepository $productRepo,
        private CarrierRepository $carrierRepo
    )
    {
        $this->em = $em;
    }

    private function session(): SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            throw new \LogicException('CartService utilisé hors requête HTTP.');
        }

        // Déclenche une exception claire si la session est désactivée/stateless
        return $request->getSession();
    }

    public function get($key)
    {
        return $this->session()->get($key, []);
    }

    public function update($key, $cart)
    {
        return $this->session()->set($key, $cart);
    }

    public function addToCart($productId, $count = 1)
    {
        $product = $this->productRepo->find($productId);
        if (!$product) {
            throw new \Exception("Produit non trouvé.");
        }

        // Vérifier si le stock est suffisant pour la quantité demandée
        $availableStock = $product->getStock();
        if ($availableStock < $count) {
            throw new \Exception("Stock insuffisant pour le produit demandé.");
        }

        // Calculer le nouveau stock
        $newStock = $availableStock - $count;

        // Il n'est pas nécessaire de vérifier si newStock est négatif ici,
        // car nous avons déjà vérifié que $count ne dépasse pas $availableStock
        $product->setStock($newStock);

        $this->em->persist($product);
        $this->em->flush();

        // Ajouter le produit au panier dans la session
        $cart = $this->get('cart');
        if (isset($cart[$productId])) {
            $cart[$productId] += $count;
        } else {
            $cart[$productId] = $count;
        }
        $this->update("cart", $cart);
    }

    public function removeToCart($productId, $count = 1)
    {
        $cart = $this->get("cart");

        if (isset($cart[$productId])) {
            $product = $this->productRepo->find($productId);
            if (!$product) {
                throw new \Exception("Produit non trouvé.");
            }

            // Calculer la nouvelle quantité à retirer et ajuster le stock en conséquence
            $actualCountToRemove = $cart[$productId] <= $count ? $cart[$productId] : $count;
            $newStock = $product->getStock() + $actualCountToRemove;
            $product->setStock($newStock);

            // Sauvegarder le changement de stock
            $this->em->persist($product);
            $this->em->flush();

            // Ajuster la quantité dans le panier ou supprimer le produit du panier
            if ($cart[$productId] <= $count) {
                unset($cart[$productId]);
            } else {
                $cart[$productId] -= $count;
            }

            $this->update("cart", $cart);
        }
    }

    public function clearCart()
    {
        $this->update("cart", []);
    }

    public function updateCarrier($carrier)
    {
        $this->update("carrier", $carrier);
    }
    public function getCartDetails()
    {

        $cart = $this->get('cart');
        $result = [
            'items' => [],
            'sub_total' => 0,
            'cart_count' => 0,
            'quantity' => 0,
        ];

        $sub_total = 0;
        $taxe_rate = 0;


        foreach ($cart as $productId => $quantity) {
            $product = $this->productRepo->find($productId);
            if ($product) {
                $current_sub_total = $product->getSoldePrice() * $quantity;
                $sub_total += $current_sub_total;
                $result['items'][] =
                    [
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
                        'sub_total_ht' => round($current_sub_total / (1 + $taxe_rate)),
                        'taxe' => round($taxe_rate * $current_sub_total / (1 + $taxe_rate)),
                        'sub_total' => $current_sub_total,
                    ];
                $result['sub_total'] = $sub_total;
                $result['sub_total_ht'] = round($sub_total / (1 + $taxe_rate));
                $result['taxe'] = round($taxe_rate * $result['sub_total_ht']);
                $result['cart_count'] += $quantity;
                $result['quantity'] += $quantity;
            } else {
                unset($cart[$productId]);
                $this->update("cart", $cart);
            }
        }
        $carrier = $this->get('carrier');

        if (!$carrier) {
            $defaultCarrierEntity = $this->carrierRepo->findOneBy([]);

            if (!$defaultCarrierEntity) {
                // Aucun transporteur en base -> fallback propre
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


        // Si le sous-total est supérieur à 50, les frais de transport sont offerts
        if ($result['sub_total'] > 5900) {
            $carrier["price"] = 0;
        }

        $result["carrier"] =  $carrier;
        $result['sub_total_with_carrier'] = $result['sub_total'] + $carrier["price"];

        return $result;
    }

    public function isStockSufficient($productId, $quantity): bool
    {
        $product = $this->productRepo->find($productId);
        if (!$product) {
            // Produit non trouvé
            return false;
        }

        return $product->getStock() >= $quantity;
    }
}
