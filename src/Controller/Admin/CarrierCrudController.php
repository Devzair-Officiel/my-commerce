<?php

namespace App\Controller\Admin;

use App\Entity\Carrier;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class CarrierCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Carrier::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Transporteur')
            ->setEntityLabelInPlural('Transporteurs')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'name', 'description'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(25);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_EDIT, Action::INDEX)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, [Action::EDIT, Action::DETAIL, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        /**
         * ===========================
         * INDEX — simple & utile
         * ===========================
         */
        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('name', 'Nom');
            yield MoneyField::new('price', 'Prix')
                ->setCurrency('EUR');

            return;
        }

        /**
         * ===========================
         * FORM — structuré
         * ===========================
         */
        yield FormField::addTab('Infos')->setIcon('fa fa-truck');
        yield FormField::addPanel('Informations générales')->setIcon('fa fa-pen-to-square');

        yield TextField::new('name', 'Nom')
            ->setColumns(6)
            ->setHelp('Nom affiché côté client (checkout, pages).');

        yield MoneyField::new('price', 'Prix')
            ->setColumns(6)
            ->setCurrency('EUR')
            ->setHelp('Frais de livraison TTC, affichés au client.');

        yield FormField::addPanel('Description')->setIcon('fa fa-align-left');

        yield TextEditorField::new('description', 'Description')
            ->setColumns(12)
            ->setHelp('Optionnel : infos utiles (délais, conditions, zones).');
    }
}
