<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\Civility;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

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
            ->setDefaultSort(['id' => 'DESC'])
            ->setSearchFields(['id', 'email', 'full_name'])
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

        // INDEX : sobre & efficace
        if (Crud::PAGE_INDEX === $pageName) {
            yield IdField::new('id');
            yield EmailField::new('email', 'Email');
            yield TextField::new('full_name', 'Nom complet');
            yield ChoiceField::new('roles', 'Rôles')
                ->allowMultipleChoices()
                ->renderExpanded(false)
                ->setChoices([
                    'Utilisateur' => 'ROLE_USER',
                    'Admin' => 'ROLE_ADMIN',
                ]);
            yield BooleanField::new('isVerified', 'Email vérifié');
            return;
        }

        // =========================
        // TAB 1 — IDENTITÉ
        // =========================
        yield FormField::addTab('Identité')->setIcon('fa fa-user');

        // Layout 2 colonnes sur ce tab
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

        yield ChoiceField::new('civility', 'Civilité')
            ->setChoices($this->getCivilityChoices())
            ->setRequired(false)
            ->setColumns(6);

        // =========================
        // TAB 2 — SÉCURITÉ
        // =========================
        yield FormField::addColumn(4);
        yield FormField::addFieldset('Sécurité')->setIcon('fa fa-shield-halved')->collapsible();

        yield BooleanField::new('isVerified', 'Email vérifié')
            ->setHelp('Indique si l’utilisateur a confirmé son email.');

        // Champ non mappé, géré manuellement (persist/update)
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

        // =========================
        // TAB 3 — DROITS
        // =========================
        yield FormField::addTab('Droits')->setIcon('fa fa-user-gear');

        yield FormField::addFieldset('Rôles & permissions')->setIcon('fa fa-key')->collapsible();

        yield ChoiceField::new('roles', 'Rôles')
            ->allowMultipleChoices()
            ->renderExpanded(false)
            ->setChoices([
                'Utilisateur' => 'ROLE_USER',
                'Admin' => 'ROLE_ADMIN',
                // Ajoute tes rôles ici
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
