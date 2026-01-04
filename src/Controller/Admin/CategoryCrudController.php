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
use EasyCorp\Bundle\EasyAdminBundle\Field\FileField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
        $isForm = \in_array($pageName, [Crud::PAGE_NEW, Crud::PAGE_EDIT], true);

        yield IdField::new('id')->hideOnForm();

        if ($isForm) {
            yield FormField::addTab('Informations');
            yield FormField::addColumn(8);

            yield TextField::new('title', 'Titre')->setRequired(true);

            yield SlugField::new('slug')
                ->setTargetFieldName('title')
                ->hideOnIndex();

            yield TextEditorField::new('description')
                ->setFormTypeOption('attr', ['rows' => 8]);

            yield BooleanField::new('isMega', 'Mega menu');

            yield FormField::addColumn(4);
            yield FormField::addPanel('Image');

            // ✅ UNIQUE champ image (upload + preview)
            yield ImageField::new('media.filename', 'Image')
                ->setUploadDir(self::UPLOAD_DIR)
                ->setBasePath(self::BASE_PATH)
                ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
                ->setRequired(false);
        } else {
            yield TextField::new('title');
            yield BooleanField::new('isMega');

            yield ImageField::new('media.filename', 'Image')
                ->setBasePath(self::BASE_PATH)
                ->onlyOnIndex();
        }
    }


    public function createEntity(string $entityFqcn): Category
    {
        /** @var Category $category */
        $category = new Category();

        // Important: créer le Media tout de suite pour éviter les null
        $media = new Media();
        $category->setMedia($media);

        return $category;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Category $category */
        $category = $entityInstance;

        // Sécurité: si jamais media est null, on le recrée
        if ($category->getMedia() === null) {
            $category->setMedia(new Media());
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
