<?php

namespace App\Controller\Admin;

use App\Entity\Carrier;
use App\Enum\CarrierType;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Contrôleur EasyAdmin pour la gestion CRUD des transporteurs (domicile, point relais, manuel).
 */
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
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
        

        /**
         * ===========================
         * INDEX — simple & utile
         * ===========================
         */
        // Sur l'index : ChoiceField avec valeurs string pour l'affichage
        $typeChoices = array_combine(
            array_map(fn(CarrierType $t) => $t->label(), CarrierType::cases()),
            array_map(fn(CarrierType $t) => $t->value, CarrierType::cases()),
        );

        // Sur les formulaires : EnumType Symfony qui gère string ↔ enum nativement
        $typeFormField = fn(string $label) => ChoiceField::new('type', $label)
            ->setFormType(\Symfony\Component\Form\Extension\Core\Type\EnumType::class)
            ->setFormTypeOptions(['class' => CarrierType::class, 'choice_label' => fn(CarrierType $t) => $t->label()]);

        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id');
            yield TextField::new('name', 'Nom');
            yield ChoiceField::new('type', 'Type')
                ->setChoices($typeChoices)
                ->formatValue(fn ($v) => $v instanceof CarrierType ? $v->label() : (string) $v)
                ->renderAsBadges(['manual' => 'secondary', 'colissimo' => 'primary']);
            yield MoneyField::new('price', 'Prix')->setCurrency('EUR');
            yield BooleanField::new('hasPickupPoints', 'Points relais')->renderAsSwitch(false);

            return;
        }

        /**
         * ===========================
         * FORM — structuré
         * ===========================
         */
        yield FormField::addTab('Infos')->setIcon('fa fa-truck');
        yield FormField::addPanel('Informations générales')->setIcon('fa fa-pen-to-square');

        yield IdField::new('id')->hideOnForm();
        yield TextField::new('name', 'Nom')
            ->setColumns(6)
            ->setHelp('Nom affiché côté client (checkout, pages).');

        yield $typeFormField('Type API')
            ->setColumns(6)
            ->setHelp('Manuel = suivi saisi à la main. Colissimo = génération automatique étiquette (domicile + points relais).');

        yield MoneyField::new('price', 'Prix')
            ->setColumns(4)
            ->setCurrency('EUR')
            ->setHelp('Frais de livraison TTC, affichés au client.');

        yield TextField::new('deliveryDelay', 'Délai indicatif')
            ->setColumns(4)
            ->setHelp('Ex : "2-3 jours ouvrés". Affiché au client au checkout.');

        yield BooleanField::new('hasPickupPoints', 'Points relais')
            ->setColumns(4)
            ->setHelp('Activer si ce transporteur permet de choisir un point relais.');

        yield FormField::addPanel('Description')->setIcon('fa fa-align-left');

        yield TextEditorField::new('description', 'Description')
            ->setColumns(12)
            ->setHelp('Optionnel : infos utiles (délais, conditions, zones).');
    }
}
