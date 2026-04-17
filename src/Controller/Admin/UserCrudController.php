<?php

namespace App\Controller\Admin;

use App\Entity\Address;
use App\Entity\Order;
use App\Entity\User;
use App\Entity\Wishlist;
use App\Enum\Civility;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\NumberField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TelephoneField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Contrôleur EasyAdmin pour la gestion des comptes utilisateurs (clients et administrateurs).
 * Accessible uniquement aux super administrateurs.
 */
#[IsGranted('ROLE_SUPER_ADMIN')]
final class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Utilisateur')
            ->setEntityLabelInPlural('Utilisateurs')
            ->setPageTitle(Crud::PAGE_INDEX, 'Liste des utilisateurs')
            ->setPageTitle(Crud::PAGE_DETAIL, 'Détails utilisateur')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['id', 'email', 'full_name', 'firstname', 'lastname', 'phone'])
            ->showEntityActionsInlined();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::DETAIL)
            ->add(Crud::PAGE_EDIT, Action::INDEX);
    }

    public function configureFields(string $pageName): iterable
    {
        $isNew = $pageName === Crud::PAGE_NEW;

        // INDEX : liste moderne
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id')->onlyOnIndex();
            yield EmailField::new('email', 'Email')->setColumns(4);
            yield TextField::new('full_name', 'Nom complet')->setColumns(3);
            yield TelephoneField::new('phone', 'Téléphone')->setColumns(2)->setRequired(false);
            yield DateTimeField::new('createdAt', 'Inscrit le')->setColumns(3)->setFormat('dd/MM/yyyy HH:mm');
            yield AssociationField::new('orders', 'Commandes')->formatValue(fn ($value, $entity) => count($value ?? []))->setColumns(2);
            yield ArrayField::new('roles', 'Rôles')->setColumns(2);
            yield BooleanField::new('isVerified', 'Vérifié')->setColumns(2);
            return;
        }

        // DETAIL : vue complète avec tabs
        if (Crud::PAGE_DETAIL === $pageName) {
            yield FormField::addTab('Identité')->setIcon('fa-solid fa-user');
            yield FormField::addFieldset('Coordonnées')->setIcon('fa-solid fa-id-card');
            yield IdField::new('id');
            yield EmailField::new('email', 'Email')->setColumns(6);
            yield TextField::new('full_name', 'Nom complet')->setColumns(6);
            yield TextField::new('firstname', 'Prénom')->setColumns(6);
            yield TextField::new('lastname', 'Nom')->setColumns(6);
            yield TelephoneField::new('phone', 'Téléphone')->setColumns(12);
            yield ChoiceField::new('civility', 'Civilité')->setColumns(6);

            yield FormField::addTab('Compte')->setIcon('fa-solid fa-shield-halved');
            yield FormField::addFieldset('Sécurité')->setIcon('fa-solid fa-lock');
            yield BooleanField::new('isVerified', 'Email vérifié');
            yield ArrayField::new('roles', 'Rôles')->setColumns(12);

            yield FormField::addTab('Relations')->setIcon('fa-solid fa-link');
            yield FormField::addFieldset('Activités')->setIcon('fa-solid fa-list');
            yield AssociationField::new('orders', 'Commandes')->setColumns(12);
            yield AssociationField::new('addresses', 'Adresses')->setColumns(12);
            yield AssociationField::new('wishlist', 'Liste de souhaits')->setColumns(12);

            yield FormField::addTab('Dates')->setIcon('fa-solid fa-calendar');
            yield DateTimeField::new('createdAt', 'Créé le')->setFormat('dd/MM/yyyy HH:mm');
            yield DateTimeField::new('updatedAt', 'Mis à jour le')->setFormat('dd/MM/yyyy HH:mm');

            return;
        }

        // FORMS (NEW/EDIT) : existant
        yield FormField::addTab('Identité')->setIcon('fa fa-user');
        yield FormField::addColumn(8);
        yield FormField::addFieldset('Informations')->setIcon('fa fa-id-card')->collapsible();
        yield IdField::new('id')->hideOnForm();
        yield EmailField::new('email', 'Email')
            ->setHelp('Utilisé pour la connexion.')
            ->setFormTypeOption('attr', ['autocomplete' => 'email']);
        yield TextField::new('full_name', 'Nom complet')
            ->setRequired(false)
            ->setHelp('Affichage (optionnel).')
            ->setFormTypeOption('attr', ['autocomplete' => 'name']);
        yield TextField::new('firstname', 'Prénom')->setColumns(6);
        yield TextField::new('lastname', 'Nom')->setColumns(6);
        yield TelephoneField::new('phone', 'Téléphone')->setColumns(12);
        yield ChoiceField::new('civility', 'Civilité')
            ->setChoices($this->getCivilityChoices())
            ->setRequired(false)
            ->setColumns(6);

        yield FormField::addColumn(4);
        yield FormField::addFieldset('Sécurité')->setIcon('fa fa-shield-halved')->collapsible();
        yield BooleanField::new('isVerified', 'Email vérifié')
            ->setHelp('Indique si l’utilisateur a confirmé son email.');
        yield TextField::new('plainPassword', $isNew ? 'Mot de passe' : 'Nouveau mot de passe')
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => $isNew,
                'first_options' => [
                    'label' => $isNew ? 'Mot de passe' : 'Nouveau mot de passe',
                    'attr' => [
                        'autocomplete' => $isNew ? 'new-password' : 'new-password',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer',
                    'attr' => [
                        'autocomplete' => $isNew ? 'new-password' : 'new-password',
                    ],
                ],
                'invalid_message' => 'Les mots de passe doivent être identiques.',
                'constraints' => $isNew
                    ? [
                        new NotBlank(message: 'Veuillez saisir un mot de passe.'),
                        new Length(min: 6, max: 4096, minMessage: 'Minimum {{ limit }} caractères.'),
                    ]
                    : [
                        new Length(min: 6, max: 4096, minMessage: 'Minimum {{ limit }} caractères.'),
                    ],
            ])
            ->setHelp($isNew ? 'Minimum 6 caractères.' : 'Laisse vide pour conserver le mot de passe actuel.')
            ->onlyOnForms();

        yield FormField::addTab('Droits')->setIcon('fa fa-user-gear');
        yield FormField::addFieldset('Rôles & permissions')->setIcon('fa fa-key')->collapsible();
        yield ChoiceField::new('roles', 'Rôles')
            ->allowMultipleChoices()
            ->renderExpanded(false)
            ->setChoices([
                'Utilisateur' => 'ROLE_USER',
                'Admin' => 'ROLE_ADMIN',
            ])
            ->setHelp('Attention : les rôles donnent accès à des fonctionnalités sensibles.');
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            parent::persistEntity($entityManager, $entityInstance);
            return;
        }

        $this->handlePassword($entityInstance);

        if (!$entityInstance->getPassword()) {
            throw new \RuntimeException('Mot de passe manquant : impossible de créer l’utilisateur.');
        }

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            parent::updateEntity($entityManager, $entityInstance);
            return;
        }

        $this->handlePassword($entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function handlePassword(User $user): void
    {
        $context = $this->getContext();
        if (!$context instanceof AdminContext) {
            return;
        }

        $userData = $context->getRequest()->request->all('User');
        $plainPassword = $userData['plainPassword'] ?? null;

        if (is_array($plainPassword)) {
            $plainPassword = $plainPassword['first'] ?? null; // RepeatedType
        }

        if (is_string($plainPassword) && $plainPassword !== '') {
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        }
    }

    private function getCivilityChoices(): array
    {
        $choices = [];
        foreach (Civility::cases() as $case) {
            $label = method_exists($case, 'label') ? $case->label() : $case->name;
            $choices[$label] = $case;
        }

        return $choices;
    }
}
