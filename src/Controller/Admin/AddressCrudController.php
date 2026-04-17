<?php

namespace App\Controller\Admin;

use App\Entity\Address;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Contrôleur EasyAdmin pour la gestion CRUD des adresses de livraison et de facturation des clients.
 */
final class AddressCrudController extends AbstractCrudController
{
    private const ADDRESS_TYPE_CHOICES = [
        'Facturation' => 'Facturation',
        'Livraison' => 'Livraison',
    ];

    public static function getEntityFqcn(): string
    {
        return Address::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Adresse')
            ->setEntityLabelInPlural('Adresses')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields([
                'id',
                'name',
                'client_name',
                'street',
                'code_postal',
                'city',
                'state',
                'address_type',
                'user.email', 
            ])
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
        

        /**
         * =====================================================
         * INDEX — simple & lisible
         * =====================================================
         */
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id');
            yield TextField::new('client_name', 'Client');
            yield TextField::new('name', 'Libellé');
            yield ChoiceField::new('address_type', 'Type')
                ->setChoices(self::ADDRESS_TYPE_CHOICES)
                ->renderAsBadges([
                    'Facturation' => 'info',
                    'Livraison' => 'success',
                ]);

            yield TextField::new('city', 'Ville');
            yield CountryField::new('state', 'Pays');
            yield AssociationField::new('user', 'Utilisateur');

            return;
        }

        /**
         * =====================================================
         * FORM / DETAIL — structuré (Tabs + Panels)
         * =====================================================
         */

        // ===== TAB 1 : ADRESSE =====
        yield FormField::addTab('Adresse')->setIcon('fa fa-address-card');

        

        yield FormField::addPanel('Identification')
            ->setIcon('fa fa-tag')
            ->setHelp('Nom interne de l’adresse et informations du destinataire.');

        yield IdField::new('id')->hideOnForm();

        yield TextField::new('name', 'Libellé')
            ->setColumns(6)
            ->setRequired(true)
            ->setHelp('Ex: "Maison", "Bureau", "Chez maman"...');

        yield TextField::new('client_name', 'Nom du client')
            ->setColumns(6)
            ->setRequired(true);

        yield ChoiceField::new('address_type', 'Type d’adresse')
            ->setChoices(self::ADDRESS_TYPE_CHOICES)
            ->renderAsNativeWidget()
            ->setColumns(6)
            ->setHelp('Facturation ou livraison.');

        yield FormField::addPanel('Coordonnées')
            ->setIcon('fa fa-map-marker-alt')
            ->setHelp('Adresse postale utilisée pour les envois et/ou la facturation.');

        yield TextField::new('street', 'Rue')
            ->setColumns(12)
            ->setRequired(true);

        yield TextField::new('code_postal', 'Code postal')
            ->setColumns(4)
            ->setRequired(false)
            ->setHelp('Optionnel si ton parcours ne l’impose pas.');

        yield TextField::new('city', 'Ville')
            ->setColumns(4)
            ->setRequired(true);

        yield CountryField::new('state', 'Pays')
            ->setColumns(4)
            ->setRequired(true);

        // ===== TAB 2 : UTILISATEUR =====
        yield FormField::addTab('Utilisateur')->setIcon('fa fa-user');

        yield FormField::addPanel('Rattachement')
            ->setIcon('fa fa-link')
            ->setHelp('Cette adresse appartient à un compte client (obligatoire).');

        yield AssociationField::new('user', 'Utilisateur')
            ->setColumns(6)
            ->setRequired(true);

        // ===== TAB 3 : NOTES =====
        yield FormField::addTab('Notes')->setIcon('fa fa-note-sticky');

        yield FormField::addPanel('Détails complémentaires')
            ->setIcon('fa fa-circle-info')
            ->setHelp('Infos de livraison : digicode, étage, bâtiment, consignes…');

        yield TextareaField::new('more_details', 'Plus de détails')
            ->setColumns(12)
            ->setRequired(false)
            ->setNumOfRows(6)
            ->hideOnIndex();
    }
}
