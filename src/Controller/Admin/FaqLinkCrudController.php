<?php

namespace App\Controller\Admin;

use App\Entity\FaqLink;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class FaqLinkCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return FaqLink::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Lien utile FAQ')
            ->setEntityLabelInPlural('Liens utiles FAQ')
            ->setPageTitle(Crud::PAGE_INDEX, 'Liens utiles — sidebar FAQ')
            ->setDefaultSort(['position' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield IntegerField::new('position', 'Ordre')->setColumns(2)->setHelp('0 = en premier');
        yield TextField::new('label', 'Libellé')->setColumns(5);
        yield TextField::new('url', 'URL')->setColumns(5)->setHelp('Chemin relatif (ex : /blog/comment-choisir-son-miel) ou URL absolue.');
        yield TextField::new('description', 'Description courte')->setColumns(12)->setRequired(false);
        yield BooleanField::new('isActive', 'Visible')->renderAsSwitch(true);
    }
}
