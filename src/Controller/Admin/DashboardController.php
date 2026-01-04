<?php

namespace App\Controller\Admin;

use App\Entity\Page;
use App\Entity\User;
use App\Entity\Carrier;
use App\Entity\Product;
use App\Entity\Setting;
use App\Entity\Sliders;
use App\Entity\Category;
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
        $productCount = (int) $this->em->getRepository(Product::class)->count([]);
        $userCount = (int) $this->em->getRepository(User::class)->count([]);
        $categoryCount = (int) $this->em->getRepository(Category::class)->count([]);

        // Si tu as 1 seule ligne Setting, on récupère la dernière / unique
        $setting = $this->em->getRepository(Setting::class)->findOneBy([], ['id' => 'DESC']);

        $urls = [
            'products' => $this->adminUrlGenerator->setController(ProductCrudController::class)->generateUrl(),
            'users' => $this->adminUrlGenerator->setController(UserCrudController::class)->generateUrl(),
            'categories' => $this->adminUrlGenerator->setController(CategoryCrudController::class)->generateUrl(),
            'settings_index' => $this->adminUrlGenerator->setController(SettingCrudController::class)->generateUrl(),
        ];

        // Si setting existe, on génère direct l’URL d’édition
        $urls['settings_edit'] = $setting
            ? $this->adminUrlGenerator
            ->setController(SettingCrudController::class)
            ->setAction('edit')
            ->setEntityId((string) $setting->getId())
            ->generateUrl()
            : null;

        return $this->render('admin/dashboard.html.twig', [
            'counts' => [
                'products' => $productCount,
                'users' => $userCount,
                'categories' => $categoryCount,
            ],
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
        yield MenuItem::linkToCrud('Produits', 'fas fa-list', Product::class);
        yield MenuItem::linkToCrud('Utilisateurs', 'fas fa-users', User::class);
        yield MenuItem::linkToCrud('Categories', 'fas fa-tag', Category::class);
        yield MenuItem::linkToCrud('Sliders', 'fas fa-image', Sliders::class);
        yield MenuItem::linkToCrud('Page', 'fas fa-file', Page::class);
        yield MenuItem::linkToCrud('Transporteurs', 'fas fa-car', Carrier::class);
        yield MenuItem::linkToCrud('Setting', 'fas fa-gear', Setting::class);
    }
}
