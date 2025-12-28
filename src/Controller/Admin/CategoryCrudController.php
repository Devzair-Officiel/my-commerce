<?php

namespace App\Controller\Admin;

use App\Entity\Category;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
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
            // TextEditorField::new('description')
            //     ->setFormType(CKEditorType::class)
            //     ->setFormTypeOptions([
            //         'config' => [
            //             'height' => '200px',
            //         ],
            //     ]),
            BooleanField::new('isMega'),
            ImageField::new('imageUrl')
                ->setBasePath("/assets/images/categories")
                ->setUploadDir("/public/assets/images/categories")
                ->setUploadedFileNamePattern('[randomhash].[extension]')
        ];
    }

    // public function configureCrud(Crud $crud): Crud
    // {
    //     return $crud->addFormTheme('@FOSCKEditor/Form/ckeditor_widget.html.twig');
    // }
}
