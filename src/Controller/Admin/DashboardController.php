<?php

namespace App\Controller\Admin;

use App\Entity\Page;
use App\Entity\User;
use App\Entity\Order;
use App\Entity\Address;
use App\Entity\Carrier;
use App\Entity\Product;
use App\Entity\Setting;
use App\Entity\Sliders;
use App\Entity\Category;
use App\Entity\PaymentMethod;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
final class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {}

    public function index(): Response
    {
        $counts = [
            'products' => (int) $this->em->getRepository(Product::class)->count([]),
            'users' => (int) $this->em->getRepository(User::class)->count([]),
            'categories' => (int) $this->em->getRepository(Category::class)->count([]),
            'pages' => (int) $this->em->getRepository(Page::class)->count([]),
            'sliders' => (int) $this->em->getRepository(Sliders::class)->count([]),
            'paymentMethods' => (int) $this->em->getRepository(PaymentMethod::class)->count([]),
            'carriers' => (int) $this->em->getRepository(Carrier::class)->count([]),
        ];

        $setting = $this->em->getRepository(Setting::class)->findOneBy([], ['id' => 'DESC']);

        // ⚠️ AdminUrlGenerator est stateful → clone pour chaque URL
        $urls = [
            'products' => (clone $this->adminUrlGenerator)->setController(ProductCrudController::class)->generateUrl(),
            'users' => (clone $this->adminUrlGenerator)->setController(UserCrudController::class)->generateUrl(),
            'categories' => (clone $this->adminUrlGenerator)->setController(CategoryCrudController::class)->generateUrl(),

            'pages' => (clone $this->adminUrlGenerator)->setController(PageCrudController::class)->generateUrl(),
            'sliders' => (clone $this->adminUrlGenerator)->setController(SlidersCrudController::class)->generateUrl(),
            'paymentMethods' => (clone $this->adminUrlGenerator)->setController(PaymentMethodCrudController::class)->generateUrl(),
            'carriers' => (clone $this->adminUrlGenerator)->setController(CarrierCrudController::class)->generateUrl(),

            'settings_index' => (clone $this->adminUrlGenerator)->setController(SettingCrudController::class)->generateUrl(),
        ];

        $urls['settings_edit'] = $setting
            ? (clone $this->adminUrlGenerator)
            ->setController(SettingCrudController::class)
            ->setAction('edit')
            ->setEntityId((string) $setting->getId())
            ->generateUrl()
            : null;

        return $this->render('admin/dashboard.html.twig', [
            'counts' => $counts,
            'setting' => $setting,
            'urls' => $urls,
        ]);
    }


    public function configureAssets(): Assets
    {
        return Assets::new()->addCssFile('assets/css/admin.css');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('App');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');

        yield MenuItem::section('Catalogue');
        yield MenuItem::linkToCrud('Produits', 'fa fa-box', Product::class);
        yield MenuItem::linkToCrud('Catégories', 'fa fa-tags', Category::class);

        yield MenuItem::section('Contenu');
        yield MenuItem::linkToCrud('Pages', 'fa fa-file', Page::class);
        yield MenuItem::linkToCrud('Sliders', 'fa fa-image', Sliders::class);

        yield MenuItem::section('Vente');
        yield MenuItem::linkToCrud('Commandes', 'fas fa-shopping-cart', Order::class);
        yield MenuItem::linkToCrud('Modes de paiement', 'fa fa-credit-card', PaymentMethod::class);
        yield MenuItem::linkToCrud('Transporteurs', 'fa fa-truck', Carrier::class);

        yield MenuItem::section('Utilisateurs');
        yield MenuItem::linkToCrud('Utilisateurs', 'fa fa-users', User::class);
        yield MenuItem::linkToCrud('Adresses', 'fas fa-address-card', Address::class);

        yield MenuItem::section('Configuration');
        yield MenuItem::linkToCrud('Réglages', 'fa fa-gear', Setting::class);

    }
}
