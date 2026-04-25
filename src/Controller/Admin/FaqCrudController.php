<?php

namespace App\Controller\Admin;

use App\Entity\Faq;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class FaqCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Faq::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Question FAQ')
            ->setEntityLabelInPlural('FAQ')
            ->setPageTitle(Crud::PAGE_INDEX, 'Gestion de la FAQ')
            ->setDefaultSort(['position' => 'ASC'])
            ->showEntityActionsInlined();
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield IntegerField::new('position', 'Ordre')
            ->setColumns(2)
            ->setHelp('0 = en premier');
        yield TextField::new('question', 'Question')
            ->setColumns(10);
        yield TextareaField::new('answer', 'Réponse')
            ->setColumns(12)
            ->setFormTypeOption('attr', ['rows' => 4]);
        yield BooleanField::new('isActive', 'Visible')
            ->renderAsSwitch(true);
        yield BooleanField::new('isWholesale', 'Page grossiste')
            ->renderAsSwitch(true)
            ->setHelp('Activé → affiché sur /grossiste-miel. Désactivé → affiché sur /faq-miel.');
    }
}
