<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;

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
            // ->add('full_name', TextType::class, [
            //     'label' => false,
            //     'attr' => [
            //         'placeholder' => 'Full Name',
            //         'class' => 'form-control'
            //     ],
            //     'row_attr' => [
            //         'class' => 'form-group mb-3'
            //     ]
            // ])
            ->add('agreeTerms', CheckboxType::class, [
                'mapped' => false,
                'attr' => [
                    'class' => 'form-check-input'
                ],
                'constraints' => [
                    new IsTrue(message: 'Vous devez accepter les conditions.')
                ],
                'row_attr' => [
                    'class' => 'custome-checkbox d-flex gap-2'
                ]
            ])
            ->add('password', RepeatedType::class, [
                'type'=> PasswordType::class,
                'mapped' => false,
                'attr' => ['autocomplete' => 'new-password'],
                'constraints' => [
                    new NotBlank(message: 'Entrer un mot de passe'),
                    new Length(
                        min: 6,
                        max: 4096,
                        minMessage: 'Votre mot de passe doit contenir minimum {{ limit }} caractère',
                    ),
                ],
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
            'data_class' => User::class,
        ]);
    }
}
