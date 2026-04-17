<?php

namespace App\Controller\Admin;

use App\Entity\Contact;
use App\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/**
 * Contrôleur EasyAdmin pour la consultation des messages reçus via le formulaire de contact.
 */
class ContactCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Contact::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Message contact')
            ->setEntityLabelInPlural('Messages contact')
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des messages')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Détails du message')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['email', 'subject', 'content'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFields(string $pageName): iterable
    {
        $id = IdField::new('id')->onlyOnIndex();

        $email = EmailField::new('email', 'Email')
            ->setColumns(6);

        $phone = TelephoneField::new('phone', 'Téléphone')
            ->setColumns(6)
            ->setRequired(false);

        $subject = TextField::new('subject', 'Sujet')
            ->setColumns(6);

        $contentSnippet = TextField::new('content', 'Message')
            ->onlyOnIndex()
            ->setColumns(12)
            ->formatValue(function ($value, $entity): string {
                $content = $entity->getContent() ?? '';
                return substr(strip_tags($content), 0, 100) . '...';
            });

        $contentFull = TextEditorField::new('content', 'Contenu')
            ->setColumns(12);

        $userField = AssociationField::new('user', 'Utilisateur')
            ->setColumns(6)
            ->formatValue(function ($value, $entity): string {
                $user = $entity->getUser();
                if (!$user) {
                    return 'Anonyme';
                }
                return $user->getEmail() ?? 'Utilisateur sans email';
            });

        $createdAt = DateTimeField::new('createdAt', 'Créé le')
            ->setColumns(6)
            ->setFormat('dd/MM/yyyy HH:mm');

        $updatedAt = DateTimeField::new('updatedAt', 'Mis à jour le')
            ->setColumns(6)
            ->setFormat('dd/MM/yyyy HH:mm');

        if (Crud::PAGE_INDEX === $pageName) {
            return [
                $id,
                $email,
                $subject,
                $phone,
                $userField,
                $createdAt,
                $contentSnippet,
            ];
        }

        // Detail view with tabs like SettingCrudController
        return [
            FormField::addTab('Expéditeur')->setIcon('fa-solid fa-envelope'),
            FormField::addFieldset('Coordonnées')->setIcon('fa-solid fa-address-book'),
            $email,
            $phone,

            FormField::addFieldset('Message')->setIcon('fa-solid fa-comment-dots'),
            $subject,
            $contentFull,

            FormField::addTab('Utilisateur')->setIcon('fa-solid fa-user'),
            $userField,

            FormField::addTab('Dates')->setIcon('fa-solid fa-calendar'),
            $createdAt,
            $updatedAt,
        ];
    }
}
