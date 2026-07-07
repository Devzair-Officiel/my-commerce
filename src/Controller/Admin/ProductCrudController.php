<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Product;
use App\Form\MediaType;
use App\Service\MediaFileManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CodeEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;

/**
 * Contrôleur EasyAdmin pour la gestion CRUD des produits (miels), incluant les médias, le stock, le prix et les champs SEO.
 */
final class ProductCrudController extends AbstractCrudController
{
    public function __construct(private readonly MediaFileManager $files) {}

    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile('assets/js/admin-char-counter.js');
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Produit')
            ->setEntityLabelInPlural('Produits')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'title', 'slug', 'brand'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(25);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(NumericFilter::new('stock', 'Stock'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::DETAIL);

        if (!$this->isGranted('ROLE_ADMIN')) {
            $actions
                ->disable(Action::NEW, Action::DELETE)
                ->setPermission(Action::DETAIL, 'ROLE_OPERATOR')
                ->setPermission(Action::EDIT, 'ROLE_OPERATOR');
        }

        return $actions;
    }

    public function configureFields(string $pageName): iterable
    {
        $isOperatorOnly = !$this->isGranted('ROLE_ADMIN');

        // =========================
        // INDEX
        // =========================
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id');

            yield ImageField::new('coverFilename', 'Image')
                ->setBasePath('/assets/images/products')
                ->setSortable(false);

            yield TextField::new('title', 'Titre');

            yield MoneyField::new('regular_price', 'Prix normal')->setCurrency('EUR');
            yield MoneyField::new('solde_price', 'Prix promo')->setCurrency('EUR');

            yield IntegerField::new('stock', 'Stock')
                ->setTemplatePath('admin/fields/stock_badge.html.twig')
                ->setSortable(true);

            yield BooleanField::new('isAvailable', 'Disponible');
            yield BooleanField::new('isBestSeller', 'Incontournable');
            yield BooleanField::new('isNewArrival', 'Nouveauté');
            // yield BooleanField::new('isSpecialOffer', 'Offre spéciale');


            return;
        }


        // =========================
        // FORMS
        // =========================

        // Opérateur : uniquement le champ stock, en lecture contextuelle
        if ($isOperatorOnly) {
            yield TextField::new('title', 'Produit')->setFormTypeOption('disabled', true)->setColumns(12);
            yield IntegerField::new('stock', 'Stock')->setColumns(4)->setHelp('Seul ce champ est modifiable.');
            return;
        }

        // ----- TAB Général
        yield FormField::addTab('Général')->setIcon('fa fa-box');
        yield FormField::addFieldset('Identité')->setIcon('fa fa-id-card')->collapsible();

        yield IdField::new('id')->hideOnForm();

        yield TextField::new('title', 'Titre')->setColumns(7);

        yield SlugField::new('slug', 'Slug')
            ->setTargetFieldName('title')
            ->setColumns(5)
            ->hideOnIndex();


        // yield TextField::new('brand', 'Marque')->setColumns(4);
        yield IntegerField::new('weightGrams', 'Poid')
            ->setHelp('Mettre le poid net en gramme')
            ->setColumns(3);

        
        yield CountryField::new('originCountry', "Pays d'origine")->setColumns(4);
        yield TextField::new('tastingProfile', 'Profil gustatif')
            ->setHelp("Max 120 caractères. Ex. : Un miel dense et aromatique, aux notes herbacées franches, avec une douceur chaude et une belle persistance en bouche.")
            ->setMaxLength(120)
            ->setColumns(5);



        // ----- TAB Contenu
        yield FormField::addTab('Contenu')->setIcon('fa fa-align-left');
        yield FormField::addFieldset('Description courte')->setIcon('fa fa-pen')->collapsible();

        yield TextField::new('accroche', 'Accroche')
            ->setHelp('Phrase courte affichée sous le titre — résume le caractère du produit en une ligne.')
            ->setColumns(12);
        yield CodeEditorField::new('description', 'Description courte')
            ->setColumns(12)
            ->setLanguage('xml');

        yield FormField::addFieldset('Description détaillée')
            ->setIcon('fa fa-align-left')
            ->collapsible()
            ->renderCollapsed();

        yield CodeEditorField::new('more_description', 'Description détaillée')
            ->setColumns(12)
            ->setLanguage('xml');

        yield FormField::addFieldset('Infos additionnelles')
            ->setIcon('fa fa-circle-info')
            ->collapsible()
            ->renderCollapsed();

        yield TextEditorField::new('additional_infos', '')->setColumns(12);

        // ----- TAB Prix & Stock
        yield FormField::addTab('Prix & Stock')->setIcon('fa fa-euro-sign');
        yield FormField::addFieldset('Tarification')->setIcon('fa fa-tags')->collapsible();

        yield MoneyField::new('regular_price', 'Prix normal')->setCurrency('EUR')->setColumns(4);
        yield MoneyField::new('solde_price', 'Prix promo')->setCurrency('EUR')->setColumns(4);

        yield IntegerField::new('stock', 'Stock')
            ->setHelp('Vide = stock non géré / illimité (selon ta règle actuelle).')
            ->setColumns(4);

        // ----- TAB Relations
        yield FormField::addTab('Relations')->setIcon('fa fa-folder-tree');
        yield FormField::addFieldset('Catégories')
            ->setIcon('fa fa-layer-group')
            ->collapsible()
            ->renderCollapsed()
            ->setHelp('Optionnel — les catégories ne sont pas obligatoires.');

        yield AssociationField::new('categories', 'Catégories')
            ->setRequired(false)
            ->setFormTypeOptions(['by_reference' => false]);

        yield FormField::addFieldset('Produit lié')
            ->setIcon('fa fa-link')
            ->collapsible()
            ->renderCollapsed();

        yield AssociationField::new('relatedProducts', 'Produit lié');

        // ----- TAB Médias
        yield FormField::addTab('Médias')->setIcon('fa fa-images');

        yield FormField::addFieldset('Galerie')->setIcon('fa fa-camera')->collapsible();

        yield CollectionField::new('medias', 'Images')
            ->setEntryType(MediaType::class)
            ->onlyOnForms()
            ->setFormTypeOptions(['by_reference' => false])
            ->allowAdd()
            ->allowDelete()
            ->setEntryIsComplex(true)
            ->renderExpanded(true);

        // ----- TAB SEO & Visibilité
        yield FormField::addTab('Vente en gros')->setIcon('fa fa-truck-ramp-box');

        yield FormField::addFieldset('Activation')->setIcon('fa fa-toggle-on')->collapsible();
        yield BooleanField::new('isWholesaleAvailable', 'Disponible en gros')->setColumns(4)
            ->setHelp('Active ce produit dans la section "Miels en gros".');
        yield IntegerField::new('wholesaleMinQtyKg', 'Quantité minimale (kg)')->setColumns(4)
            ->setHelp('Ex : 5 pour 5 kg minimum par commande.');

        yield FormField::addFieldset('Paliers de prix')->setIcon('fa fa-tags')->collapsible();
        yield CollectionField::new('wholesaleTiers', 'Paliers de prix')
            ->setEntryType(\App\Form\WholesaleTierType::class)
            ->setColumns(12)
            ->allowAdd()
            ->allowDelete()
            ->setHelp('Ajoutez autant de paliers que nécessaire. Ils seront triés par quantité croissante.');

        yield FormField::addFieldset('Texte professionnel')->setIcon('fa fa-align-left')->collapsible();
        yield TextEditorField::new('wholesaleDescription', 'Résumé professionnel')
            ->setColumns(12)
            ->setHelp('Ce texte remplace "Informations complémentaires" sur la fiche grossiste. Angle pro : conditionnement, usage, stockage…');

        yield FormField::addTab('SEO & Visibilité')->setIcon('fa fa-bullhorn');

        yield FormField::addFieldset('Visibilité')->setIcon('fa fa-eye')->collapsible();
        yield BooleanField::new('isAvailable', 'Disponible')->setColumns(3);
        yield BooleanField::new('isBestSeller', 'Best seller')->setColumns(3);
        yield BooleanField::new('isNewArrival', 'Nouveauté')->setColumns(3);
        yield BooleanField::new('isFeatured', 'Mis en avant')->setColumns(3);
        yield BooleanField::new('isSpecialOffer', 'Offre spéciale')->setColumns(3);

        yield FormField::addFieldset('SEO')
            ->setIcon('fa fa-magnifying-glass')
            ->collapsible();

        yield TextField::new('seoTitle', 'SEO title')->setColumns(6);
        yield TextField::new('seoDescription', 'SEO description')->setColumns(6);
        yield BooleanField::new('seoNoindex', 'Noindex')->setColumns(3);
        yield TextField::new('seoOgImage', 'OG image')->setColumns(6);
        yield TextField::new('seoCanonicalOverride', 'Canonical override')->setColumns(6);
    }

    public function persistEntity(EntityManagerInterface $em, $entity): void
    {
        if ($entity instanceof Product) {
            $this->handleMediaUploads($entity);
        }

        parent::persistEntity($em, $entity);
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof Product) {
            $this->handleMediaUploads($entityInstance);
        }

        parent::updateEntity($em, $entityInstance);
    }

    private function handleMediaUploads(Product $product): void
    {
        // Optionnel mais très utile: garantir UNE seule cover
        $coverAlreadySet = false;

        foreach ($product->getMedias() as $media) {
            if (!$media instanceof Media) {
                continue;
            }

            $file = $media->getUpload();
            $hasFile = $file instanceof UploadedFile;
            $hasFilename = is_string($media->getFilename()) && $media->getFilename() !== '';

            // ligne vide => suppression
            if (!$hasFile && !$hasFilename) {
                $product->removeMedia($media);
                continue;
            }

            if ($hasFile) {
                $newFilename = $this->files->storeProductFile($file, $product);
                $media->setFilename($newFilename);
                $media->setUpload(null);
            }

            // relation
            if ($media->getProduct() !== $product) {
                $media->setProduct($product);
            }

            // unicité cover (soft)
            if ($media->isCover() === true) {
                if ($coverAlreadySet) {
                    $media->setIsCover(false);
                } else {
                    $coverAlreadySet = true;
                }
            }
        }

        // fallback: si aucune cover explicitement, on peut mettre la première image comme cover
        if (!$coverAlreadySet) {
            foreach ($product->getMedias() as $media) {
                $name = $media->getFilename();
                if (is_string($name) && $name !== '') {
                    $media->setIsCover(true);
                    break;
                }
            }
        }

        // Normalisation des positions : MySQL trie NULL en premier en ASC,
        // ce qui rend l'ordre incohérent quand une ancienne image sans position
        // cohabite avec une nouvelle image positionnée. On remplit les NULL
        // avec la valeur suivante (max explicite + 1), sans écraser les positions
        // choisies par l'utilisateur.
        $explicitPositions   = [];
        $mediasWithoutPos    = [];
        foreach ($product->getMedias() as $media) {
            $name = $media->getFilename();
            if (!is_string($name) || $name === '') {
                continue;
            }
            $pos = $media->getPosition();
            if ($pos !== null) {
                $explicitPositions[] = $pos;
            } else {
                $mediasWithoutPos[] = $media;
            }
        }

        $next = $explicitPositions === [] ? 1 : (max($explicitPositions) + 1);
        foreach ($mediasWithoutPos as $media) {
            $media->setPosition($next++);
        }
    }
}
