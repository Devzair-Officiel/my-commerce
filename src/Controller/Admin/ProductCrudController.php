<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Product;
use App\Form\MediaType;
use App\Service\MediaFileManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use Symfony\Component\String\Slugger\SluggerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

final class ProductCrudController extends AbstractCrudController
{
    public function __construct(private readonly MediaFileManager $files, private readonly SluggerInterface $slugger,) {}

    public static function getEntityFqcn(): string
    {
        return Product::class;
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

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
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

            yield IntegerField::new('stock', 'Stock');

            yield BooleanField::new('isAvailable', 'Disponible');
            yield BooleanField::new('isBestSeller', 'Incontournable');
            yield BooleanField::new('isNewArrival', 'Nouveauté');
            // yield BooleanField::new('isSpecialOffer', 'Offre spéciale');


            return;
        }


        // =========================
        // FORMS
        // =========================

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
            ->setColumns(4);

        
        yield CountryField::new('originCountry', 'Pays d’origine')->setColumns(4);



        // ----- TAB Contenu
        yield FormField::addTab('Contenu')->setIcon('fa fa-align-left');
        yield FormField::addFieldset('Descriptions')->setIcon('fa fa-pen')->collapsible();

        yield TextEditorField::new('description', 'Description courte')->setColumns(12);
        yield TextEditorField::new('more_description', 'Description détaillée')->setColumns(12);

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
        yield FormField::addFieldset('Catégories')->setIcon('fa fa-layer-group')->collapsible();

        yield AssociationField::new('categories', 'Catégories')->setRequired(true);

        yield FormField::addFieldset('Produit lié')
            ->setIcon('fa fa-link')
            ->collapsible()
            ->renderCollapsed();

        yield AssociationField::new('relatedProducts', 'Produit lié');

        // ----- TAB Médias
        yield FormField::addTab('Médias')->setIcon('fa fa-images');

        yield FormField::addFieldset('Aperçu')->setIcon('fa fa-image')->collapsible();
        yield ImageField::new('coverFilename', 'Image principale')
            ->setBasePath('/assets/images/products')
            ->hideOnForm();

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
    }
}
