<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/**
 * Contrôleur EasyAdmin pour la gestion CRUD des médias (images) associés aux produits et catégories.
 */
class MediaCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Media::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),

            ImageField::new('filename')
                ->setBasePath('/assets/images/products')
                ->setUploadDir('public/assets/images/products')
                // ->setUploadedFileNamePattern('[slug].[extension]') // ou autre
                ->setRequired($pageName === 'new'),

            AssociationField::new('product')->setRequired(false),
            AssociationField::new('category')->setRequired(false),

            IntegerField::new('position'),
            BooleanField::new('isCover'),
        ];
    }
}
