<?php

namespace App\Controller\Admin;

use App\Controller\Admin\ContactCrudController;
use App\Entity\Page;
use App\Entity\User;
use App\Entity\Order;
use App\Entity\Address;
use App\Entity\Carrier;
use App\Entity\Contact;
use App\Entity\Product;
use App\Entity\ProductLot;
use App\Entity\Setting;
use App\Entity\Sliders;
use App\Entity\Category;
use App\Entity\PaymentMethod;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use App\Controller\Admin\Dashboard\DashboardMetricsProvider;
use App\Entity\Blog;
use App\Entity\Faq;
use App\Entity\FaqLink;
use App\Entity\Invoice;
use App\Entity\Review;
use App\Entity\Shipment;
use App\Enum\PaymentStatus;
use App\Enum\ReviewStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

/**
 * Point d'entrée principal du back-office EasyAdmin, affiche le tableau de bord
 * avec les métriques clés (commandes, chiffre d'affaires, clients) et configure le menu de navigation.
 */
#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly DashboardMetricsProvider $metrics,
    ) {}

    public function index(): Response
    {
        $counts = [
            'products' => (int) $this->em->getRepository(Product::class)->count([]),
            'users' => (int) $this->em->getRepository(User::class)->count([]),
            'orders' => (int) $this->em->getRepository(Order::class)->count([]),
            'categories' => (int) $this->em->getRepository(Category::class)->count([]),
            'pages' => (int) $this->em->getRepository(Page::class)->count([]),
            'sliders' => (int) $this->em->getRepository(Sliders::class)->count([]),
            'paymentMethods' => (int) $this->em->getRepository(PaymentMethod::class)->count([]),
            'carriers' => (int) $this->em->getRepository(Carrier::class)->count([]),
        ];

        $setting = $this->em->getRepository(Setting::class)->findOneBy([], ['id' => 'DESC']);

        $urls = [
            'products'          => (clone $this->adminUrlGenerator)->setController(ProductCrudController::class)->generateUrl(),
            'users' => (clone $this->adminUrlGenerator)->setController(UserCrudController::class)->generateUrl(),
            'orders' => (clone $this->adminUrlGenerator)->setController(OrderCrudController::class)->generateUrl(),
            'categories' => (clone $this->adminUrlGenerator)->setController(CategoryCrudController::class)->generateUrl(),
            'pages' => (clone $this->adminUrlGenerator)->setController(PageCrudController::class)->generateUrl(),
            'sliders' => (clone $this->adminUrlGenerator)->setController(SlidersCrudController::class)->generateUrl(),
            'paymentMethods' => (clone $this->adminUrlGenerator)->setController(PaymentMethodCrudController::class)->generateUrl(),
            'carriers' => (clone $this->adminUrlGenerator)->setController(CarrierCrudController::class)->generateUrl(),
            'contacts' => (clone $this->adminUrlGenerator)->setController(ContactCrudController::class)->generateUrl(),
            'settings_index' => (clone $this->adminUrlGenerator)->setController(SettingCrudController::class)->generateUrl(),
        ];

        $urls['settings_edit'] = $setting
            ? (clone $this->adminUrlGenerator)
            ->setController(SettingCrudController::class)
            ->setAction('edit')
            ->setEntityId((string) $setting->getId())
            ->generateUrl()
            : null;

        $metrics = $this->metrics->getMetrics();

        $baseOrderUrl = (clone $this->adminUrlGenerator)->setController(OrderCrudController::class)->generateUrl();
        $sep = str_contains($baseOrderUrl, '?') ? '&' : '?';
        $urls['orders_to_ship']  = $baseOrderUrl . $sep . http_build_query(['filters' => [
            'paymentStatus'     => ['comparison' => '=', 'value' => \App\Enum\PaymentStatus::Paye->value],
            'fulfillmentStatus' => ['comparison' => '=', 'value' => [\App\Enum\FulfillmentStatus::Brouillon->value, \App\Enum\FulfillmentStatus::Preparation->value]],
        ]]);
        $urls['orders_pending']  = $baseOrderUrl . $sep . http_build_query(['filters' => ['paymentStatus' => ['comparison' => '=', 'value' => \App\Enum\PaymentStatus::Attente->value]]]);
        $urls['orders_failed']   = $baseOrderUrl . $sep . http_build_query(['filters' => ['paymentStatus' => ['comparison' => '=', 'value' => \App\Enum\PaymentStatus::Echoue->value]]]);
        $urls['orders_refunded'] = $baseOrderUrl . $sep . http_build_query(['filters' => ['paymentStatus' => ['comparison' => '=', 'value' => \App\Enum\PaymentStatus::Rembourse->value]]]);

        $baseProductUrl = (clone $this->adminUrlGenerator)->setController(ProductCrudController::class)->generateUrl();
        $sepP = str_contains($baseProductUrl, '?') ? '&' : '?';
        $urls['products_low_stock'] = $baseProductUrl . $sepP . http_build_query(['filters' => [
            'stock' => ['comparison' => '<=', 'value' => 5],
        ]]);

        foreach ($metrics['recentOrders'] as &$order) {
            $order['detailUrl'] = (clone $this->adminUrlGenerator)
                ->setController(OrderCrudController::class)
                ->setAction(\EasyCorp\Bundle\EasyAdminBundle\Config\Action::DETAIL)
                ->setEntityId($order['id'])
                ->generateUrl();
        }
        unset($order);

        return $this->render('admin/dashboard.html.twig', [
            'counts' => $counts,
            'setting' => $setting,
            'urls' => $urls,
            'metrics' => $metrics,
        ]);
    }

    public function configureAssets(): Assets
    {
        return Assets::new()
            ->addCssFile('assets/css/admin.css')
            ->addCssFile('https://cdn.jsdelivr.net/npm/shepherd.js@11/dist/css/shepherd.css')
            ->addJsFile('https://cdn.jsdelivr.net/npm/shepherd.js@11/dist/js/shepherd.min.js')
            ->addJsFile('assets/js/admin-tour.js');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('App');
    }

    public function configureMenuItems(): iterable
    {
        $pendingReviews = $this->em->getRepository(Review::class)->count(['status' => ReviewStatus::Pending]);
        $pendingOrders  = $this->em->getRepository(Order::class)->count(['paymentStatus' => PaymentStatus::Attente]);
        $lowStockCount  = $this->em->getRepository(Product::class)->count(['stock' => 0])
            + (int) $this->em->createQueryBuilder()
                ->select('COUNT(p.id)')
                ->from(Product::class, 'p')
                ->andWhere('p.stock > 0')->andWhere('p.stock <= 5')
                ->getQuery()->getSingleScalarResult();

        $isAdmin    = $this->isGranted('ROLE_ADMIN');
        $isOperator = $this->isGranted('ROLE_OPERATOR');

        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        // ── Catalogue ──────────────────────────────────────────────────────────
        yield MenuItem::section('Catalogue');

        $productsItem = MenuItem::linkToCrud('Produits', 'fa fa-box', Product::class);
        if ($lowStockCount > 0) {
            $productsItem->setBadge($lowStockCount, 'warning');
        }
        yield $productsItem;

        if ($isAdmin) {
            yield MenuItem::linkToCrud('Numéros de lot', 'fa fa-barcode', ProductLot::class);
            yield MenuItem::linkToCrud('Catégories', 'fa fa-tags', Category::class);
        }

        // ── Contenu (admin uniquement) ─────────────────────────────────────────
        if ($isAdmin) {
            yield MenuItem::section('Contenu');
            yield MenuItem::linkToCrud('Pages', 'fa fa-file', Page::class);
            yield MenuItem::linkToCrud('Sliders', 'fa fa-image', Sliders::class);
            yield MenuItem::linkToCrud('Blog', 'fas fa-code', Blog::class);
            yield MenuItem::linkToCrud('FAQ', 'fa fa-circle-question', Faq::class);
            yield MenuItem::linkToCrud('Liens utiles FAQ', 'fa fa-link', FaqLink::class);
        }

        // ── Vente ──────────────────────────────────────────────────────────────
        yield MenuItem::section('Vente');

        $ordersItem = MenuItem::linkToCrud('Commandes', 'fas fa-shopping-cart', Order::class);
        if ($pendingOrders > 0) {
            $ordersItem->setBadge($pendingOrders, 'danger');
        }
        yield $ordersItem;
        yield MenuItem::linkToCrud('Expéditions', 'fa fa-truck-fast', Shipment::class);

        if ($isAdmin) {
            yield MenuItem::linkToCrud('Factures', 'fa fa-file-invoice', Invoice::class);
            yield MenuItem::linkToCrud('Modes de paiement', 'fa fa-credit-card', PaymentMethod::class);
            yield MenuItem::linkToCrud('Transporteurs', 'fa fa-truck', Carrier::class);
        }

        // ── Avis ───────────────────────────────────────────────────────────────
        yield MenuItem::section('Avis');

        $reviewsItem = MenuItem::linkToCrud('Avis clients', 'fa fa-star', Review::class);
        if ($pendingReviews > 0) {
            $reviewsItem->setBadge($pendingReviews, 'warning');
        }
        yield $reviewsItem;

        // ── Utilisateurs ───────────────────────────────────────────────────────
        yield MenuItem::section('Utilisateurs');

        if ($isAdmin) {
            yield MenuItem::linkToCrud('Utilisateurs', 'fa fa-users', User::class);
            yield MenuItem::linkToCrud('Adresses', 'fas fa-address-card', Address::class);
        }
        yield MenuItem::linkToCrud('Messages', 'fas fa-envelope', Contact::class);

        // ── Configuration (admin uniquement) ──────────────────────────────────
        if ($isAdmin) {
            yield MenuItem::section('Configuration');
            yield MenuItem::linkToCrud('Réglages', 'fa fa-gear', Setting::class);
        }

        yield MenuItem::section('');
        yield MenuItem::linkToRoute('Guide d\'utilisation', 'fa fa-circle-question', 'admin_guide');
    }
}
