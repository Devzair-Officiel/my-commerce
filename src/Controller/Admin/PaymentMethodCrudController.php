<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\PaymentMethod;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Contrôleur EasyAdmin pour la gestion CRUD des moyens de paiement affichés sur le site (Stripe, virement, etc.).
 */
class PaymentMethodCrudController extends AbstractCrudController
{
    private const UPLOAD_DIR = 'public/assets/images/payment_methods_logos';
    private const BASE_PATH  = '/assets/images/payment_methods_logos';

    public static function getEntityFqcn(): string
    {
        return PaymentMethod::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        // INDEX : simple, lisible (pas de clés/API, pas de gros éditeurs)
        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('name', 'Nom');
            // Optionnel : afficher le logo sur l’index
            // yield ImageField::new('mediaPayment.filename', 'Logo')
            //     ->setBasePath(self::BASE_PATH)
            //     ->onlyOnIndex();
            return;
        }

        // ===== TAB 1 : INFOS =====
        yield FormField::addTab('Infos')->setIcon('fa fa-circle-info');
        yield FormField::addPanel('Informations générales')->setIcon('fa fa-pen-to-square');

        yield TextField::new('name', 'Nom')
            ->setColumns(6)
            ->setHelp('Nom affiché côté client (checkout, pages).');

        yield TextEditorField::new('description', 'Description courte')
            ->setColumns(6)
            ->setHelp('Résumé affiché dans l’interface client (optionnel).');

        yield TextEditorField::new('more_description', 'Description détaillée')
            ->setColumns(12)
            ->setHelp('Texte long (optionnel).');

        // ===== TAB 2 : LOGO =====
        yield FormField::addTab('Logo')->setIcon('fa fa-image');
        yield FormField::addPanel('Image / Branding')->setHelp('Formats recommandés : PNG (fond transparent) ou SVG.');

        yield ImageField::new('mediaPayment.filename', 'Logo')
            ->setColumns(12)
            ->setUploadDir(self::UPLOAD_DIR)
            ->setBasePath(self::BASE_PATH)
            ->setUploadedFileNamePattern('[randomhash].[extension]')
            ->setRequired(false);

        // ===== TAB 3 : CLÉS API =====
        yield FormField::addTab('Clés API')->setIcon('fa fa-key');
        yield FormField::addPanel('Environnement TEST')->setIcon('fa fa-vial');

        yield TextField::new('test_public_api_key', 'Public key (TEST)')
            ->setColumns(6)
            ->setHelp('Clé publique TEST.');

        yield TextField::new('test_private_api_key', 'Private key (TEST)')
            ->setColumns(6)
            ->setHelp('Clé privée TEST.')
            ->setFormTypeOption('attr', ['autocomplete' => 'off']);

        // yield FormField::addPanel('Environnement PROD')->setIcon('fa fa-rocket');

        // yield TextField::new('prod_public_api_key', 'Public key (PROD)')
        //     ->setColumns(6)
        //     ->setHelp('Clé publique PROD.');

        // yield TextField::new('prod_private_api_key', 'Private key (PROD)')
        //     ->setColumns(6)
        //     ->setHelp('Clé privée PROD.')
        //     ->setFormTypeOption('attr', ['autocomplete' => 'off']);

        // ===== TAB 4 : URLS =====
        yield FormField::addTab('URLs')->setIcon('fa fa-link');
        yield FormField::addPanel('Endpoints')->setIcon('fa fa-globe');

        yield TextField::new('testBaseUrl', 'Base URL (TEST)')
            ->setColumns(6)
            ->setHelp('Endpoint de base pour l’environnement TEST.');

        // yield TextField::new('prodBaseUrl', 'Base URL (PROD)')
        //     ->setColumns(6)
        //     ->setHelp('Endpoint de base pour l’environnement PROD.');
    }

    public function createEntity(string $entityFqcn): PaymentMethod
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setMediaPayment(new Media());

        return $paymentMethod;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var PaymentMethod $paymentMethod */
        $paymentMethod = $entityInstance;

        if ($paymentMethod->getMediaPayment() === null) {
            $paymentMethod->setMediaPayment(new Media());
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
