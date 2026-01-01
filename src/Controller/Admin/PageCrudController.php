<?php

namespace App\Controller\Admin;

use App\Entity\Page;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

final class PageCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Page')
            ->setEntityLabelInPlural('Pages')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'title', 'slug', 'content'])
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
        yield IdField::new('id')->hideOnForm();

        // --- Bloc principal (look moderne : structure claire)
        yield FormField::addPanel('Contenu')
            ->setIcon('fa fa-file-lines');

        yield TextField::new('title', 'Titre')
            ->setRequired(true)
            ->setHelp('Titre visible (SEO + affichage).');

        yield SlugField::new('slug', 'Slug')
            ->setTargetFieldName('title')
            ->hideOnIndex()
            ->setHelp('Généré depuis le titre. Ajuste-le uniquement si nécessaire.');

        // Pour un contenu “CMS-like” : un gros champ, hors index
        yield TextareaField::new('content', 'Contenu (HTML/texte)')
            ->hideOnIndex()
            ->renderAsHtml()
            ->setNumOfRows(18)
            ->setHelp('Tu peux mettre du HTML. Évite les scripts. (Sécurité/XSS)');

        // --- Bloc options
        yield FormField::addPanel('Emplacements')
            ->setIcon('fa fa-location-dot')
            ->hideOnIndex();

        yield BooleanField::new('isHead', 'Afficher en header')
            ->setHelp('Active si cette page doit apparaître dans le menu du haut.');

        yield BooleanField::new('isFoot', 'Afficher en footer')
            ->setHelp('Active si cette page doit apparaître dans le footer.');

        // --- Sur l’index, on veut quelque chose de lisible
        // (Les champs ci-dessous s’affichent aussi sur index si pas hideOnIndex)
        if ($pageName === Crud::PAGE_INDEX) {
            // Rien de plus à yield ici : title + booléens + id suffisent.
            // content & slug sont déjà hideOnIndex.
        }
    }
}
