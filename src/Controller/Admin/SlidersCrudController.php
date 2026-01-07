<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Sliders;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;

final class SlidersCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Sliders::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Slider')
            ->setEntityLabelInPlural('Sliders')
            ->setPageTitle(Crud::PAGE_INDEX, 'Sliders')
            ->setPageTitle(Crud::PAGE_NEW, 'Créer un slider')
            ->setPageTitle(Crud::PAGE_EDIT, fn(Sliders $s) => sprintf('Modifier : %s', $s->getTitle() ?: 'Slider'))
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'title', 'description', 'button_text', 'button_link'])
            ->showEntityActionsInlined()
            ->setPaginatorPageSize(25);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->reorder(Crud::PAGE_INDEX, [Action::EDIT, Action::DETAIL, Action::DELETE]);
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->hideOnForm();

        /**
         * =====================================================
         * INDEX — simple et lisible
         * =====================================================
         */
        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('title', 'Titre');

            yield TextField::new('button_text', 'CTA')
                ->formatValue(static fn($v) => $v ?: '—');

            yield UrlField::new('button_link', 'Lien')
                ->formatValue(static fn($v) => $v ?: '—');

            // Optionnel : afficher l’image sur l’index
            // yield ImageField::new('mediaSlider.filename', 'Image')
            //     ->setBasePath('/assets/images/sliders')
            //     ->onlyOnIndex();

            return;
        }

        /**
         * =====================================================
         * FORMULAIRE — NEW / EDIT
         * =====================================================
         */

        // ===== TAB 1 : CONTENU =====
        yield FormField::addTab('Contenu')
            ->setIcon('fa fa-circle-info');

        yield FormField::addPanel('Informations générales')
            ->setIcon('fa fa-pen-to-square');

        yield TextField::new('title', 'Titre')
            ->setColumns(6)
            ->setHelp('Optionnel. Court et impactant.');

        yield TextEditorField::new('description', 'Description')
            ->setColumns(6)
            ->setHelp('Optionnel. Évite les textes trop longs pour garder un rendu propre.');

        // ===== TAB 2 : BOUTON =====
        yield FormField::addTab('Bouton')
            ->setIcon('fa fa-bullhorn');

        yield FormField::addPanel('Call-to-action (CTA)')
            ->setIcon('fa fa-hand-pointer')
            ->setHelp('Optionnel. Le slider peut s’afficher sans bouton.');

        yield TextField::new('button_text', 'Texte du bouton')
            ->setColumns(6)
            ->setHelp('Ex : "Découvrir", "Acheter", "Voir la collection".')
            ->setRequired(false);

        yield UrlField::new('button_link', 'Lien du bouton')
            ->setColumns(6)
            ->setHelp('URL absolue ou chemin interne (ex : /boutique).')
            ->setRequired(false);

        // ===== TAB 3 : MEDIA =====
        yield FormField::addTab('Média')
            ->setIcon('fa fa-image');

        yield FormField::addPanel('Image du slider')
            ->setIcon('fa fa-image')
            ->setHelp('Formats recommandés : JPG, PNG ou WebP. Optimisée pour le header.');

        yield ImageField::new('mediaSlider.filename', 'Image')
            ->setColumns(12)
            ->setBasePath('/assets/images/sliders')
            ->setUploadDir('public/assets/images/sliders')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setRequired(false);
    }


    public function createEntity(string $entityFqcn): Sliders
    {
        $slider = new Sliders();
        $slider->setMediaSlider(new Media());

        return $slider;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Sliders $slider */
        $slider = $entityInstance;

        if ($slider->getMediaSlider() === null) {
            $slider->setMediaSlider(new Media());
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Sliders $slider */
        $slider = $entityInstance;

        if ($slider->getMediaSlider() === null) {
            $slider->setMediaSlider(new Media());
        }

        parent::updateEntity($entityManager, $entityInstance);
    }
}
