<?php

namespace App\Form;

use App\Dto\RegistrationInput;
use App\Enum\Civility;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;

/**
 * Formulaire d'inscription d'un nouveau client avec e-mail, civilité, nom, prénom et mot de passe.
 */
class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Entrer votre Email',
                    'class' => 'form-control'
                ],
                'row_attr' => [
                    'class' => 'form-group mb-3'
                ]
            ])
            ->add('civility', ChoiceType::class, [
                'label' => false,
                'choices' => Civility::cases(),
                'choice_label' => fn(Civility $c) => $c->label(),
                'choice_value' => fn(?Civility $c) => $c?->value,
                'placeholder' => 'Choisir votre civilité',
                'attr' => [
                    'class' => 'form-select',
                ],
                'row_attr' => [
                    'class' => 'form-group mb-3',
                ],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'attr' => ['autocomplete' => 'new-password'],
                'first_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Entrer votre mot de passe',
                        'class' => 'form-control'
                    ],
                    'row_attr' => [
                        'class' => 'form-group mb-3'
                    ]
                ],
                'second_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'Confirmer votre mot de passe',
                        'class' => 'form-control'
                    ],
                    'row_attr' => [
                        'class' => 'form-group mb-3'
                    ]
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationInput::class,
        ]);
    }
}
