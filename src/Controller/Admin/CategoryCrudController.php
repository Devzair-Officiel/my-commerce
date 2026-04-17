<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use App\Entity\Media;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Contrôleur EasyAdmin pour la gestion CRUD des catégories de produits, avec upload d'image et champs SEO.
 */
final class CategoryCrudController extends AbstractCrudController
{
    private const UPLOAD_DIR = 'public/assets/images/categories';
    private const BASE_PATH  = '/assets/images/categories';

    public static function getEntityFqcn(): string
    {
        return Category::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Catégorie')
            ->setEntityLabelInPlural('Catégories')
            ->setDefaultSort(['id' => 'DESC'])
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
        // =========================
        // INDEX
        // =========================
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id');
            yield TextField::new('title', 'Titre');
            yield BooleanField::new('isMega', 'Mega menu');

            yield ImageField::new('media.filename', 'Image')
                ->setBasePath(self::BASE_PATH)
                ->onlyOnIndex();

            return;
        }

        // =========================
        // FORM / EDIT
        // =========================
        yield FormField::addTab('Informations')->setIcon('fa fa-folder-open');

        // ---------- COLONNE GAUCHE (CONTENU) ----------
        yield FormField::addColumn(6);

        yield FormField::addFieldset('Contenu')
            ->setIcon('fa fa-pen-to-square')
            ->collapsible();

        yield IdField::new('id')->hideOnForm();

        yield TextField::new('title', 'Titre')
            ->setRequired(true)
            ->setHelp('Nom affiché sur le site.');

        yield SlugField::new('slug')
            ->setTargetFieldName('title')
            ->hideOnIndex()
            ->setHelp('Généré automatiquement depuis le titre.');


        // ---------- COLONNE DROITE (AFFICHAGE) ----------
        yield FormField::addColumn(6);

        yield FormField::addFieldset('Affichage')
            ->setIcon('fa fa-eye')
            ->collapsible();

        yield BooleanField::new('isMega', 'Mega menu')
            ->setHelp('Affiche la catégorie dans le mega menu.');

        
        yield FormField::addColumn(6);
        yield TextEditorField::new('intro', 'Introduction')
            ->setFormTypeOption('attr', ['rows' => 8])
            ->setHelp('Optionnel, petit texte au-dessus des produits.');

        yield FormField::addColumn(6);
        yield TextEditorField::new('description', 'Description')
            ->setFormTypeOption('attr', ['rows' => 8])
            ->setHelp('Optionnel, bloc SEO plus complet sous la grille.');

        // =========================
        // TAB MEDIA
        // =========================
        yield FormField::addTab('Média')->setIcon('fa fa-image');

        yield FormField::addFieldset('Image')
            ->setIcon('fa fa-camera')
            ->collapsible();

        yield ImageField::new('media.filename', 'Image')
            ->setUploadDir(self::UPLOAD_DIR)
            ->setBasePath(self::BASE_PATH)
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setRequired(false)
            ->setHelp('PNG ou JPG – carré recommandé.');

        // ----- TAB SEO & Visibilité
        yield FormField::addTab('SEO')->setIcon('fa fa-bullhorn');

        yield FormField::addFieldset('SEO')
            ->setIcon('fa fa-magnifying-glass')
            ->collapsible();

        yield TextField::new('seoTitle', 'SEO title')->setColumns(6);
        yield TextField::new('seoDescription', 'SEO description')->setColumns(6);
        yield BooleanField::new('seoNoindex', 'Noindex')->setColumns(3);
        yield TextField::new('seoOgImage', 'OG image')->setColumns(6);
        yield TextField::new('seoCanonicalOverride', 'Canonical override')->setColumns(6);
    }

    public function createEntity(string $entityFqcn): Category
    {
        $category = new Category();
        $category->setMedia(new Media());

        return $category;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Category $category */
        $category = $entityInstance;

        if ($category->getMedia() === null) {
            $category->setMedia(new Media());
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
