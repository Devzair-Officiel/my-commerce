<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Blog;
use App\Entity\Media;
use App\Form\MediaType;
use App\Service\MediaFileManager;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/**
 * Contrôleur EasyAdmin pour la gestion CRUD des articles de blog, incluant l'upload et la suppression des médias associés.
 */
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
final class BlogCrudController extends AbstractCrudController
{
    public function __construct(private readonly MediaFileManager $files) {}

    public static function getEntityFqcn(): string
    {
        return Blog::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Article de blog')
            ->setEntityLabelInPlural('Articles de blog')
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'title', 'slug'])
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
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id');
            yield TextField::new('title', 'Titre');
            yield TextField::new('slug', 'Slug');
            yield BooleanField::new('isPublished', 'Publié');

            yield ImageField::new('getMediaFilenames', 'Image')
                ->setBasePath('/assets/images/blogs');

            return;
        }
        

        yield FormField::addTab('Général')->setIcon('fa fa-newspaper');
        yield FormField::addFieldset('Identité')->setIcon('fa fa-id-card')->collapsible();

        yield IdField::new('id')->hideOnForm();

        yield TextField::new('title', 'Titre')->setColumns(6);

        yield SlugField::new('slug')
            ->setLabel('Slug')
            ->setTargetFieldName('title')
            ->hideOnIndex()
            ->setColumns(6);

        yield TextEditorField::new('description', 'Résumé')->setColumns(12);
        yield TextareaField::new('content', 'Contenu')->setColumns(12);

        yield FormField::addTab('Médias')->setIcon('fa fa-images');

        yield CollectionField::new('medias')
            ->setLabel('Images')
            ->setEntryType(MediaType::class)
            ->onlyOnForms()
            ->setFormTypeOptions([
                'by_reference' => false, // IMPORTANT pour addMedia()
            ])
            ->allowAdd()
            ->allowDelete()
            ->setEntryIsComplex(true)
            ->renderExpanded(true);

        yield FormField::addTab('Publication')->setIcon('fa fa-eye');
        yield BooleanField::new('isPublished', 'Publié')->setColumns(6);

        yield FormField::addTab('SEO')->setIcon('fa fa-magnifying-glass');

        yield FormField::addFieldset('Référencement')
            ->setIcon('fa fa-globe')
            ->setHelp('Laisse vide pour utiliser les valeurs par défaut (titre et description de l\'article).');

        yield TextField::new('seoTitle', 'Meta title')
            ->setColumns(8)
            ->setRequired(false)
            ->setHelp('Max 70 caractères. Si vide : titre de l\'article suivi du nom du site.');

        yield BooleanField::new('seoNoindex', 'Masquer des moteurs (noindex)')
            ->setColumns(4);

        yield TextareaField::new('seoDescription', 'Meta description')
            ->setColumns(12)
            ->setNumOfRows(3)
            ->setRequired(false)
            ->setHelp('Max 170 caractères. Si vide : générée depuis le résumé ou le contenu de l\'article.');

        yield TextField::new('seoOgImage', 'Image Open Graph (URL)')
            ->setColumns(8)
            ->setRequired(false)
            ->setHelp('URL de l\'image partagée sur les réseaux (1200×630 px). Si vide : première image de l\'article.');

        yield TextField::new('seoCanonicalOverride', 'URL canonique (override)')
            ->setColumns(8)
            ->setRequired(false)
            ->setHelp('Laisser vide sauf cas particulier (duplication de contenu).');
    }

    public function persistEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof Blog) {
            $this->handleBlogMediaUploads($entityInstance);
        }

        parent::persistEntity($em, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $em, $entityInstance): void
    {
        if ($entityInstance instanceof Blog) {
            $this->deleteRemovedBlogMediaFiles($em, $entityInstance);
            $this->handleBlogMediaUploads($entityInstance);
        }

        parent::updateEntity($em, $entityInstance);
    }

    /**
     * Supprime les fichiers des médias retirés de la collection blog.
     * Nécessaire car la relation ManyToMany ne déclenche pas orphanRemoval.
     */
    private function deleteRemovedBlogMediaFiles(EntityManagerInterface $em, Blog $blog): void
    {
        $uow = $em->getUnitOfWork();
        $uow->computeChangeSets();

        // Médias actuellement en base pour ce blog
        $original = $uow->getOriginalEntityData($blog);
        if (!isset($original['medias'])) {
            return;
        }

        /** @var \Doctrine\ORM\PersistentCollection $originalCollection */
        $originalCollection = $blog->getMedias();
        if (!$originalCollection instanceof \Doctrine\ORM\PersistentCollection) {
            return;
        }

        foreach ($originalCollection->getDeleteDiff() as $removedMedia) {
            if ($removedMedia instanceof Media) {
                $this->files->removeFile($removedMedia);
            }
        }
    }

    private function handleBlogMediaUploads(Blog $blog): void
    {
        foreach ($blog->getMedias() as $media) {
            if (!$media instanceof Media) {
                continue;
            }

            $file = $media->getUpload();

            if (!$file instanceof UploadedFile) {
                continue;
            }

            // Si un fichier existait déjà, on le supprime (optionnel mais pratique)
            if ($media->getFilename()) {
                $this->files->removeFile($media);
            }

            $filename = $this->files->storeBlogFile($file, $blog);
            $media->setFilename($filename);
            $media->setUpload(null);

            $blog->addMedia($media);
        }
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Blog $blog */
        $blog = $entityInstance;

        foreach ($blog->getMedias() as $media) {
            $this->files->removeFile($media);
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }
}
