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

        yield FormField::addTab('Identité');
        yield FormField::addColumn(8);
        yield FormField::addFieldset('Informations');

        yield IdField::new('id')->hideOnForm();

        yield EmailField::new('email', 'Email');

        yield TextField::new('full_name', 'Nom complet')
            ->setRequired(false);

        yield ChoiceField::new('civility', 'Civilité')
            ->setChoices($this->getCivilityChoices())
            ->setRequired(false);

        yield FormField::addColumn(4);
        yield FormField::addFieldset('Sécurité');

        yield BooleanField::new('isVerified', 'Email vérifié');

        // Champ non mappé, géré manuellement dans persist/update
        yield TextField::new('plainPassword', 'Mot de passe')
            ->setFormType(RepeatedType::class)
            ->setFormTypeOptions([
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => $isNew,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirmer',
                    'attr' => ['autocomplete' => 'new-password'],
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
            ->onlyOnForms();

        yield FormField::addTab('Droits');
        yield ChoiceField::new('roles', 'Rôles')
            ->allowMultipleChoices()
            ->renderExpanded(false)
            ->setChoices([
                'Utilisateur' => 'ROLE_USER',
                'Admin' => 'ROLE_ADMIN',
                // ajoute tes rôles ici
            ]);
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

        $userData = $context->getRequest()->request->all('User'); // ✅ clé explicite

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
        // Si Civility est un enum PHP 8.1+ :
        // Tu adaptes label()/value selon TON enum.
        $choices = [];
        foreach (Civility::cases() as $case) {
            // Si tu as une méthode label() comme tu l’avais dans ton code :
            $label = method_exists($case, 'label') ? $case->label() : $case->name;
            $choices[$label] = $case;
        }
        return $choices;
    }
}
