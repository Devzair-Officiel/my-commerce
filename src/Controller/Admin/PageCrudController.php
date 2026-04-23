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

/**
 * Contrôleur EasyAdmin pour la gestion CRUD des pages statiques du site (mentions légales, CGV, etc.).
 */
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
         * =====================================================
         * INDEX — simple & lisible
         * =====================================================
         */
        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('title', 'Titre');
            yield TextField::new('slug', 'Slug');
            yield BooleanField::new('isHead', 'Header');
            yield BooleanField::new('isFoot', 'Footer');

            return;
        }

        /**
         * =====================================================
         * FORM / DETAIL — structuré
         * =====================================================
         */

        // ===== TAB 1 : CONTENU =====
        yield FormField::addTab('Contenu')->setIcon('fa fa-file-lines');

        yield FormField::addPanel('Informations')
            ->setIcon('fa fa-pen-to-square');

        yield TextField::new('title', 'Titre')
            ->setColumns(6)
            ->setRequired(true)
            ->setHelp('Titre visible (SEO + affichage).');

        yield SlugField::new('slug', 'Slug')
            ->setColumns(6)
            ->setTargetFieldName('title')
            ->setHelp('Généré depuis le titre. Ajuste-le uniquement si nécessaire.');

        // Contenu en pleine largeur (12 colonnes)
        yield FormField::addPanel('Contenu de la page')
            ->setIcon('fa fa-align-left')
            ->setHelp('HTML autorisé. Évite les scripts (risque XSS).');

        yield TextareaField::new('content', 'Contenu (HTML/texte)')
            ->setColumns(12)
            ->renderAsHtml()
            ->setNumOfRows(18)
            ->setHelp('Tu peux mettre du HTML. Évite les scripts.');

        // ===== TAB 2 : EMPLACEMENTS =====
        yield FormField::addTab('Emplacements')->setIcon('fa fa-map-marker-alt');

        yield FormField::addPanel('Visibilité')
            ->setIcon('fa fa-eye')
            ->setHelp('Choisis où afficher cette page dans le site.');

        yield BooleanField::new('isHead', 'Afficher en header')
            ->setColumns(6)
            ->setHelp('Active si cette page doit apparaître dans le menu du haut.');

        yield BooleanField::new('isFoot', 'Afficher en footer')
            ->setColumns(6)
            ->setHelp('Active si cette page doit apparaître dans le footer.');

        // ===== TAB 3 : SEO =====
        yield FormField::addTab('SEO')->setIcon('fa fa-magnifying-glass');

        yield FormField::addPanel('Référencement')
            ->setIcon('fa fa-globe')
            ->setHelp('Laisse vide pour utiliser les valeurs par défaut (titre et description de la page).');

        yield TextField::new('seoTitle', 'Meta title')
            ->setColumns(8)
            ->setRequired(false)
            ->setHelp('Max 70 caractères. Si vide : titre de la page.');

        yield BooleanField::new('seoNoindex', 'Masquer des moteurs (noindex)')
            ->setColumns(4);

        yield TextareaField::new('seoDescription', 'Meta description')
            ->setColumns(12)
            ->setNumOfRows(3)
            ->setRequired(false)
            ->setHelp('Max 170 caractères. Résumé affiché dans les résultats Google.');

        yield TextField::new('seoOgImage', 'Image Open Graph (URL)')
            ->setColumns(8)
            ->setRequired(false)
            ->setHelp('URL de l\'image partagée sur les réseaux (1200×630 px recommandé).');

        yield TextField::new('seoCanonicalOverride', 'URL canonique (override)')
            ->setColumns(8)
            ->setRequired(false)
            ->setHelp('Laisser vide sauf cas particulier (duplication de contenu).');
    }
}
