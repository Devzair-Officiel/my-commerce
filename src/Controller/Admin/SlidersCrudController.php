<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\Sliders;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

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
        yield FormField::addTab('Contenu');
        yield IdField::new('id')->onlyOnIndex();

        yield TextField::new('title', 'Titre')
            ->setHelp('Optionnel. Court et impactant.');

        yield TextEditorField::new('description', 'Description')
            ->setHelp('Optionnel. Évite trop long pour garder un rendu propre dans le header.')
            ->hideOnIndex();

        yield FormField::addTab('Bouton');

        yield TextField::new('button_text', 'Texte du bouton')
            ->setHelp('Ex: "Découvrir", "Acheter", "Voir la collection".');

        yield UrlField::new('button_link', 'Lien du bouton')
            ->setHelp('URL absolue ou chemin interne (ex: /boutique).');

        yield FormField::addTab('Médias');

        yield ImageField::new('mediaSlider.filename', 'Image')
            ->setBasePath('/assets/images/sliders')
            ->setUploadDir('public/assets/images/sliders')
            ->setUploadedFileNamePattern('[title].[timestamp].[extension]')
            ->setRequired(false);
    }

    public function createEntity(string $entityFqcn)
    {
        /** @var Sliders $category */
        $category = new Sliders();

        // Important: créer le Media tout de suite pour éviter les null
        $media = new Media();
        $category->setMediaSlider($media);

        return $category;
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Sliders $category */
        $category = $entityInstance;

        // Sécurité: si jamais media est null, on le recrée
        if ($category->getMediaSlider() === null) {
            $category->setMediaSlider(new Media());
        }

        parent::persistEntity($entityManager, $entityInstance);
    }
}
