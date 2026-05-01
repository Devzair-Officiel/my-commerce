<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AuditLog;
use App\Enum\AuditAction;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class AuditLogCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return AuditLog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Entrée d\'audit')
            ->setEntityLabelInPlural('Journal d\'audit')
            ->setPageTitle(Crud::PAGE_INDEX, 'Journal d\'audit')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Détail de l\'entrée')
            ->setDefaultSort(['performedAt' => 'DESC'])
            ->setSearchFields(['entityClass', 'performedBy', 'entityId'])
            ->showEntityActionsInlined()
            ->setEntityPermission('ROLE_ADMIN');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('entityClass', 'Entité')->setChoices([
                'Commande'    => 'Order',
                'Produit'     => 'Product',
                'Utilisateur' => 'User',
                'Avis'        => 'Review',
                'Adresse'     => 'Address',
            ]))
            ->add(ChoiceFilter::new('action', 'Action')->setChoices([
                'Création'     => AuditAction::Create->value,
                'Modification' => AuditAction::Update->value,
                'Suppression'  => AuditAction::Delete->value,
            ]))
            ->add(TextFilter::new('performedBy', 'Par'))
            ->add(DateTimeFilter::new('performedAt', 'Date'));
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id', '#')->setMaxLength(6);

        yield ChoiceField::new('entityClass', 'Entité')
            ->setChoices([
                'Order'   => 'Order',
                'Product' => 'Product',
                'User'    => 'User',
                'Review'  => 'Review',
                'Address' => 'Address',
            ])
            ->renderAsBadges([
                'Order'   => 'primary',
                'Product' => 'info',
                'User'    => 'secondary',
                'Review'  => 'warning',
                'Address' => 'light',
            ]);

        yield IntegerField::new('entityId', 'ID entité');

        yield ChoiceField::new('action', 'Action')
            ->setChoices([
                'CREATE' => AuditAction::Create->value,
                'UPDATE' => AuditAction::Update->value,
                'DELETE' => AuditAction::Delete->value,
            ])
            ->renderAsBadges([
                AuditAction::Create->value => 'success',
                AuditAction::Update->value => 'warning',
                AuditAction::Delete->value => 'danger',
            ]);

        yield TextField::new('performedBy', 'Par');

        yield DateTimeField::new('performedAt', 'Date')
            ->setFormat('dd/MM/yyyy HH:mm:ss')
            ->setTimezone('Europe/Paris');

        if ($pageName === Crud::PAGE_DETAIL) {
            yield TextField::new('ipAddress', 'IP');
            yield TextField::new('changesRaw', 'Modifications')
                ->setTemplatePath('admin/fields/audit_changes.html.twig')
                ->onlyOnDetail();
        }
    }
}
