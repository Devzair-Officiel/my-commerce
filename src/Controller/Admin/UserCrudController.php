<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\Civility;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use Symfony\Component\Validator\Constraints\Length;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use Symfony\Component\Validator\Constraints\NotBlank;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
    ) {}

    public static function getEntityFqcn(): string
    {
        return User::class;
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
        return [
            FormField::addColumn(6),
            FormField::addFieldset(),
            IdField::new('id')->hideOnForm(),
            EmailField::new('email'),
            ChoiceField::new('civility')
                ->setChoices(array_reduce(Civility::cases(), function ($acc, $c) {
                    $acc[$c->label()] = $c;
                    return $acc;
                }, [])),
            FormField::addColumn(4),
            FormField::addFieldset( propertySuffix: 'password'),
            Field::new('plainPassword', 'Mot de passe')
                ->setFormType(RepeatedType::class)
                ->setFormTypeOptions([
                    'type' => PasswordType::class,
                    'mapped' => false,
                    'required' => $pageName === Crud::PAGE_NEW, // obligatoire à la création, optionnel à l’édition
                    'first_options' => [
                        'label' => 'Mot de passe',
                        'attr' => ['autocomplete' => 'new-password'],
                    ],
                    'second_options' => [
                        'label' => 'Confirmer le mot de passe',
                        'attr' => ['autocomplete' => 'new-password'],
                    ],
                    'invalid_message' => 'Les mots de passe doivent être identiques.',
                    'constraints' => $pageName === Crud::PAGE_NEW
                        ? [
                            new NotBlank(message: 'Veuillez saisir un mot de passe.'),
                            new Length(min: 6, max: 4096, minMessage: 'Minimum {{ limit }} caractères.'),
                        ]
                        : [
                            // En édition : si rempli, on valide la longueur. Si vide, on ne change rien.
                            new Length(min: 6, max: 4096, minMessage: 'Minimum {{ limit }} caractères.'),
                        ],
                ])
                ->onlyOnForms()
        ];
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            return;
        }

        $this->handlePassword($entityInstance);

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if (!$entityInstance instanceof User) {
            return;
        }

        $this->handlePassword($entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }

    private function handlePassword(User $user): void
    {
        /** @var AdminContext $context */
        $context = $this->getContext();

        // récupère le champ non mappé "plainPassword" soumis dans le form
        $plainPassword = $context?->getRequest()->request->all('User')['plainPassword'] ?? null;

        // Avec RepeatedType, tu peux recevoir un tableau ['first' => ..., 'second' => ...]
        if (is_array($plainPassword)) {
            $plainPassword = $plainPassword['first'] ?? null;
        }

        if (is_string($plainPassword) && $plainPassword !== '') {
            $user->setPassword(
                $this->passwordHasher->hashPassword($user, $plainPassword)
            );
        }
    }
}
