<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Product;
use App\Entity\Setting;
use App\Repository\CarrierRepository;
use App\Repository\ProductRepository;
use App\Repository\SettingRepository;
use App\Service\CartService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests unitaires pour CartService.
 *
 * Ce que l'on teste :
 *  - addToCart : produit indisponible, stock = 0, stock null (illimité), dépassement de stock
 *  - removeToCart : retrait partiel, retrait total, retrait inexistant
 *  - splitTtcIntoHtAndTax (via getCartDetails) : calcul HT + TVA en centimes
 *  - Livraison offerte : seuil atteint vs non atteint
 */
class CartServiceTest extends TestCase
{
    private CartService $cart;
    private Session $session;

    // stubs : on contrôle les retours sans vérifier les appels
    private ProductRepository $productRepo;
    private SettingRepository $settingRepo;
    private CarrierRepository $carrierRepo;

    protected function setUp(): void
    {
        // Session en mémoire — pas de BDD, pas d'HTTP
        $this->session = new Session(new MockArraySessionStorage());

        $request = new Request();
        $request->setSession($this->session);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $this->productRepo = $this->createStub(ProductRepository::class);
        $this->settingRepo = $this->createStub(SettingRepository::class);
        $this->carrierRepo = $this->createStub(CarrierRepository::class);

        // Par défaut : pas de transporteur en session → fallback null
        $this->carrierRepo->method('findOneBy')->willReturn(null);

        // Utilisateur anonyme : la persistance BDD du panier est court-circuitée
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        $this->cart = new CartService(
            $requestStack,
            $this->settingRepo,
            $this->productRepo,
            $this->carrierRepo,
            $security,
            $this->createStub(EntityManagerInterface::class),
            freeShippingThresholdCents: 5000, // 50 EUR
        );
    }

    // -------------------------------------------------------------------------
    // addToCart
    // -------------------------------------------------------------------------

    public function testAddToCartProduitInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->cart->addToCart(0);
    }

    public function testAddToCartQuantiteInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->cart->addToCart(1, 0);
    }

    public function testAddToCartProduitIntrouvable(): void
    {
        $this->productRepo->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Produit non trouvé');
        $this->cart->addToCart(42);
    }

    public function testAddToCartProduitIndisponible(): void
    {
        $product = $this->makeProduct(id: 1, stock: 10, isAvailable: false);
        $this->productRepo->method('find')->willReturn($product);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('disponible');
        $this->cart->addToCart(1);
    }

    public function testAddToCartStockZeroBloque(): void
    {
        $product = $this->makeProduct(id: 1, stock: 0, isAvailable: true);
        $this->productRepo->method('find')->willReturn($product);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stock insuffisant');
        $this->cart->addToCart(1, 1);
    }

    public function testAddToCartStockNullIllimite(): void
    {
        // stock = null → produit numérique / pas de gestion de stock → doit passer
        $product = $this->makeProduct(id: 1, stock: null, isAvailable: true);
        $this->productRepo->method('find')->willReturn($product);

        $this->cart->addToCart(1, 99);

        // Pas d'exception = succès
        $this->addToAssertionCount(1);
    }

    public function testAddToCartDepassementStock(): void
    {
        $product = $this->makeProduct(id: 1, stock: 3, isAvailable: true);
        $this->productRepo->method('find')->willReturn($product);

        $this->cart->addToCart(1, 3); // 3 = stock max, OK

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stock insuffisant');
        $this->cart->addToCart(1, 1); // 3 + 1 = 4 > 3 → doit échouer
    }

    public function testAddToCartCumulePanier(): void
    {
        $product = $this->makeProduct(id: 5, stock: 10, isAvailable: true);
        $this->productRepo->method('find')->willReturn($product);

        $this->cart->addToCart(5, 2);
        $this->cart->addToCart(5, 3);

        $raw = $this->session->get('cart');
        $this->assertSame(5, $raw[5]);
    }

    // -------------------------------------------------------------------------
    // removeToCart
    // -------------------------------------------------------------------------

    public function testRemoveToCartPartiel(): void
    {
        $this->session->set('cart', [7 => 5]);

        $this->cart->removeToCart(7, 2);

        $this->assertSame(3, $this->session->get('cart')[7]);
    }

    public function testRemoveToCartTotal(): void
    {
        $this->session->set('cart', [7 => 2]);

        $this->cart->removeToCart(7, 5); // retire plus que dispo → vide le slot

        $this->assertArrayNotHasKey(7, $this->session->get('cart'));
    }

    public function testRemoveToCartProduitAbsent(): void
    {
        $this->session->set('cart', []);

        // Ne doit pas lever d'exception
        $this->cart->removeToCart(99, 1);
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Calcul TVA — splitTtcIntoHtAndTax testé via getCartDetails
    // -------------------------------------------------------------------------

    public function testCalculTvaVingtPourcent(): void
    {
        // 1 produit à 1200 centimes TTC, TVA 20%
        // HT attendu = round(1200 * 100 / 120) = 1000
        // TVA attendue = 200
        $this->setupGetCartDetailsEnv(
            productId: 1,
            priceCents: 1200,
            qty: 1,
            taxRate: 20,
        );

        $details = $this->cart->getCartDetails();

        $this->assertSame(1000, $details['sub_total_ht']);
        $this->assertSame(200, $details['taxe']);
        $this->assertSame(1200, $details['sub_total']);
    }

    public function testCalculTvaCinqSeptPourcent(): void
    {
        // 1 produit à 107 centimes TTC, TVA 7%
        // HT = intdiv(107*100 + 53, 107) = intdiv(10753, 107) = 100
        // TVA = 107 - 100 = 7
        $this->setupGetCartDetailsEnv(
            productId: 2,
            priceCents: 107,
            qty: 1,
            taxRate: 7,
        );

        $details = $this->cart->getCartDetails();

        $this->assertSame(100, $details['sub_total_ht']);
        $this->assertSame(7, $details['taxe']);
    }

    public function testCalculTvaZero(): void
    {
        $this->setupGetCartDetailsEnv(
            productId: 3,
            priceCents: 500,
            qty: 1,
            taxRate: 0,
        );

        $details = $this->cart->getCartDetails();

        $this->assertSame(500, $details['sub_total_ht']);
        $this->assertSame(0, $details['taxe']);
    }

    public function testCalculAvecQuantiteMultiple(): void
    {
        // 3 produits à 1000 centimes TTC, TVA 20%
        // Total TTC = 3000, HT = 2500, TVA = 500
        $this->setupGetCartDetailsEnv(
            productId: 4,
            priceCents: 1000,
            qty: 3,
            taxRate: 20,
        );

        $details = $this->cart->getCartDetails();

        $this->assertSame(3000, $details['sub_total']);
        $this->assertSame(2500, $details['sub_total_ht']);
        $this->assertSame(500, $details['taxe']);
    }

    // -------------------------------------------------------------------------
    // Livraison offerte
    // -------------------------------------------------------------------------

    public function testLivraisonOfferteSeuiAtteint(): void
    {
        // seuil = 5000 centimes (50 EUR), panier = 5000 → livraison offerte
        $this->setupGetCartDetailsEnv(
            productId: 5,
            priceCents: 5000,
            qty: 1,
            taxRate: 0,
            carrierPrice: 500,
        );

        $details = $this->cart->getCartDetails();

        $this->assertSame(0, $details['carrier']['price']);
        $this->assertTrue($details['carrier']['free_shipping']);
        $this->assertSame(5000, $details['sub_total_with_carrier']);
    }

    public function testLivraisonPayanteSeuiNonAtteint(): void
    {
        // panier = 4999 < 5000 → transporteur facturé
        $this->setupGetCartDetailsEnv(
            productId: 6,
            priceCents: 4999,
            qty: 1,
            taxRate: 0,
            carrierPrice: 500,
        );

        $details = $this->cart->getCartDetails();

        $this->assertSame(500, $details['carrier']['price']);
        $this->assertFalse($details['carrier']['free_shipping']);
        $this->assertSame(5499, $details['sub_total_with_carrier']);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeProduct(int $id, ?int $stock, bool $isAvailable): Product
    {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getStock')->willReturn($stock);
        $product->method('isAvailable')->willReturn($isAvailable);
        $product->method('isOnSale')->willReturn(false);
        $product->method('getSoldePrice')->willReturn(null);
        $product->method('getRegularPrice')->willReturn(1000);
        $product->method('getWeightGrams')->willReturn(0);
        $product->method('getMediaData')->willReturn([]);

        return $product;
    }

    private function makeSetting(int $taxRate): Setting
    {
        $setting = $this->createStub(Setting::class);
        $setting->method('getTaxeRate')->willReturn($taxRate);
        $setting->method('getFreeShippingThresholdCents')->willReturn(10000);

        return $setting;
    }

    private function setupGetCartDetailsEnv(
        int $productId,
        int $priceCents,
        int $qty,
        int $taxRate,
        int $carrierPrice = 0,
    ): void {
        $product = $this->createStub(Product::class);
        $product->method('getId')->willReturn($productId);
        $product->method('getStock')->willReturn(100);
        $product->method('isAvailable')->willReturn(true);
        $product->method('isOnSale')->willReturn(false);
        $product->method('getSoldePrice')->willReturn(null);
        $product->method('getRegularPrice')->willReturn($priceCents);
        $product->method('getWeightGrams')->willReturn(0);
        $product->method('getMediaData')->willReturn([]);
        $product->method('getTitle')->willReturn('Produit test');
        $product->method('getDescription')->willReturn('Description');
        $product->method('getSlug')->willReturn('produit-test');

        $this->productRepo->method('findBy')->willReturn([$product]);
        $this->settingRepo->method('findOneBy')->willReturn($this->makeSetting($taxRate));

        // Transporteur en session avec le prix défini
        $this->session->set('carrier', [
            'id' => 1,
            'name' => 'Colissimo',
            'description' => '',
            'price' => $carrierPrice,
        ]);

        // Panier en session
        $this->session->set('cart', [$productId => $qty]);
    }
}
