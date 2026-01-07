<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class EditPasswordFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false,
                'attr' => [
                    'autocomplete' => 'current-password',
                    'class' => 'form-control',
                    'placeholder' => 'Entrez votre mot de passe actuel',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez entrer votre mot de passe actuel.'),
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'invalid_message' => 'Les deux mots de passe ne correspondent pas.',
                'first_options' => [
                    'label' => false,
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'Entrez votre nouveau mot de passe…',
                        'class' => 'form-control',
                    ],
                    'row_attr' => ['class' => 'form-group mb-3'],
                ],
                'second_options' => [
                    'label' => false,
                    'attr' => [
                        'autocomplete' => 'new-password',
                        'placeholder' => 'Confirmez votre nouveau mot de passe…',
                        'class' => 'form-control',
                    ],
                    'row_attr' => ['class' => 'form-group mb-3'],
                ],
                // ✅ contraintes appliquées au champ interne
                'options' => [
                    'constraints' => [
                        new NotBlank(message: 'Veuillez entrer un nouveau mot de passe.'),
                        new Length(
                            min: 6,
                            max: 4096,
                            minMessage: 'Votre mot de passe doit contenir au moins {{ limit }} caractères.'
                        ),
                    ],
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => \App\Entity\User::class,
        ]);
    }
}
