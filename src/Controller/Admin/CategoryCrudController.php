<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use FOS\CKEditorBundle\Form\Type\CKEditorType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

class CategoryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Category::class;
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
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('title'),
            SlugField::new('slug')->setTargetFieldName('title'),
            TextEditorField::new('description'),
            BooleanField::new('isMega'),
            // AssociationField::new('media')->renderAsEmbeddedForm()
            // Upload sur Media.filename
            ImageField::new('media.filename', 'Image')
                ->setBasePath('/assets/images/categories')
                ->setUploadDir('public/assets/images/categories')
                ->setUploadedFileNamePattern('[slug].[extension]')
                ->setRequired(false),
        ];
    }


    public function createEntity(string $entityFqcn)
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
