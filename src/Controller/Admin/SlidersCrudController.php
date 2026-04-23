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

/**
 * Contrôleur EasyAdmin pour la gestion CRUD des sliders affichés sur la page d'accueil.
 */
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

        if (Crud::PAGE_INDEX === $pageName) {
            yield TextField::new('title', 'Titre');
            yield TextField::new('button_text', 'CTA')->formatValue(static fn($v) => $v ?: '—');
            yield UrlField::new('button_link', 'Lien')->formatValue(static fn($v) => $v ?: '—');
            return;
        }

        yield FormField::addTab('Contenu')->setIcon('fa fa-circle-info');
        yield FormField::addPanel('Informations générales')->setIcon('fa fa-pen-to-square');
        yield TextField::new('title', 'Titre')->setColumns(6)->setHelp('Optionnel. Court et impactant.');
        yield TextEditorField::new('description', 'Description')->setColumns(6)->setHelp('Optionnel.');

        yield FormField::addTab('Bouton')->setIcon('fa fa-bullhorn');
        yield FormField::addPanel('Call-to-action (CTA)')->setIcon('fa fa-hand-pointer');
        yield TextField::new('button_text', 'Texte du bouton')->setColumns(6)->setRequired(false);
        yield UrlField::new('button_link', 'Lien du bouton')->setColumns(6)->setRequired(false);

        yield FormField::addTab('Média')->setIcon('fa fa-image');
        yield FormField::addPanel('Image du slider')->setIcon('fa fa-image')->setHelp('Formats recommandés : JPG, PNG ou WebP.');
        yield ImageField::new('mediaSlider.filename', 'Image desktop')
            ->setColumns(6)
            ->setBasePath('/assets/images/sliders')
            ->setUploadDir('public/assets/images/sliders')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setHelp('Format paysage recommandé (ex. 1920×800).')
            ->setRequired(false);
        yield ImageField::new('mediaSliderMobile', 'Image mobile')
            ->setColumns(6)
            ->setBasePath('/assets/images/sliders')
            ->setUploadDir('public/assets/images/sliders')
            ->setUploadedFileNamePattern('[slug]-mobile-[timestamp].[extension]')
            ->setHelp('Format portrait recommandé (ex. 768×1024). Si vide, l\'image desktop est utilisée.')
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
