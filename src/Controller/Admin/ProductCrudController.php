<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Product;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class ProductCrudController extends AbstractCrudController
{
    public function __construct(private MediaFileManager $files) {}

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
            ->setSearchFields(['id', 'title', 'slug'])
            ->showEntityActionsInlined();
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
        // ---------- INDEX (liste) : sobre & efficace ----------
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id');

            yield TextField::new('title')->setLabel('Titre');
            yield MoneyField::new('solde_price')->setLabel('Prix promo')->setCurrency('EUR');
            yield MoneyField::new('regular_price')->setLabel('Prix normal')->setCurrency('EUR');
            yield IntegerField::new('stock')->setLabel('Stock');

            yield AssociationField::new('categories')->setLabel('Catégories');

            // Thumbnail (si ta méthode getMediaFilenames renvoie quelque chose de pertinent)
            yield ImageField::new('getMediaFilenames', 'Image')
                ->setBasePath('/assets/images/products');

            yield BooleanField::new('isBestSeller')->setLabel('Best seller');
            yield BooleanField::new('isNewArrival')->setLabel('Nouveauté');
            yield BooleanField::new('isFeatured')->setLabel('Mis en avant');
            yield BooleanField::new('isSpecialOffer')->setLabel('Offre spéciale');

            return;
        }

        // ---------- FORMS / DETAIL ----------
        yield FormField::addTab('Général')
            ->setIcon('fa fa-box');

        yield FormField::addFieldset('Identité')
            ->setIcon('fa fa-id-card')
            ->collapsible();

        yield IdField::new('id')->hideOnForm();

        yield TextField::new('title')->setLabel('Titre')->setColumns(6);

        yield SlugField::new('slug')
            ->setLabel('Slug')
            ->setTargetFieldName('title')
            ->hideOnIndex()
            ->setColumns(6);

        yield TextEditorField::new('description')
            ->setLabel('Description')
            ->setColumns(12);

        yield FormField::addFieldset('Informations supplémentaires')
            ->setIcon('fa fa-circle-info')
            ->renderCollapsed();

        yield TextEditorField::new('additional_infos')
            ->setLabel('')
            ->setColumns(12)
            ->hideOnIndex();

        yield FormField::addTab('Prix & Stock')
            ->setIcon('fa fa-euro-sign');

        yield FormField::addFieldset('Tarification')
            ->setIcon('fa fa-tags')
            ->collapsible();

        yield MoneyField::new('solde_price')
            ->setLabel('Prix promo')
            ->setCurrency('EUR')
            ->setColumns(4);

        yield MoneyField::new('regular_price')
            ->setLabel('Prix normal')
            ->setCurrency('EUR')
            ->setColumns(4);

        yield IntegerField::new('stock')
            ->setLabel('Stock')
            ->setColumns(4);

        yield FormField::addTab('Catégories & Relations')
            ->setIcon('fa fa-folder-tree');

        yield FormField::addFieldset('Catégorisation')
            ->setIcon('fa fa-layer-group')
            ->collapsible();

        yield AssociationField::new('categories')
            ->setLabel('Catégories')
            ->setRequired(true);

        yield FormField::addFieldset('Produits liés')
            ->setIcon('fa fa-link')
            ->collapsible()
            ->renderCollapsed();

        yield AssociationField::new('relatedProducts')
            ->setLabel('Produits liés');

        yield FormField::addTab('Médias')
            ->setIcon('fa fa-images');

        yield FormField::addFieldset('Images du produit')
            ->setIcon('fa fa-camera')
            ->collapsible();

        yield ImageField::new('getMediaFilenames', 'Images')
            ->setBasePath('/assets/images/products')
            ->hideOnForm();

        yield CollectionField::new('medias')
            ->setLabel('Ajouter / modifier des médias')
            ->setEntryType(\App\Form\MediaType::class)
            ->onlyOnForms()
            ->setFormTypeOptions([
                'by_reference' => false,
            ])
            ->allowAdd()
            ->allowDelete()
            ->setEntryIsComplex(true)
            ->renderExpanded(true);

        yield FormField::addTab('Visibilité')
            ->setIcon('fa fa-eye');

        yield FormField::addFieldset('Badges')
            ->setIcon('fa fa-badge-check') // si cette icône n’existe pas chez toi, remplace par 'fa fa-certificate'
            ->collapsible();

        yield BooleanField::new('isBestSeller')->setLabel('Best seller');
        yield BooleanField::new('isNewArrival')->setLabel('Nouveauté');
        yield BooleanField::new('isFeatured')->setLabel('Mis en avant');
        yield BooleanField::new('isSpecialOffer')->setLabel('Offre spéciale');
    }

    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof Product) {
            $this->handleMediaUploads($entityInstance);
        }

        parent::persistEntity($em, $entityInstance);
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
        foreach ($product->getMedias() as $media) {
            if (!$media instanceof Media) {
                continue;
            }

            // 1) Si l’admin a ajouté une ligne media mais sans fichier et sans filename => on la supprime
            $file = $media->getUpload();
            $hasFile = $file instanceof UploadedFile;
            $hasFilename = (string) $media->getFilename() !== '';

            if (!$hasFile && !$hasFilename) {
                $product->removeMedia($media); // ou removeMedia() selon ton nom de méthode
                continue;
            }

            // 2) Si un fichier est uploadé => on génère filename et on l’écrit en base
            if ($hasFile) {
                $newFilename = $this->files->storeProductFile($file, $product); // à implémenter
                $media->setFilename($newFilename);
                $media->setUpload(null); // optionnel, évite de garder l’objet en mémoire
            }

            // 3) sécurité relation
            if ($media->getProduct() !== $product) {
                $media->setProduct($product);
            }
        }
    }
}
