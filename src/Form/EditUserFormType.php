<?php

namespace App\Form;

use App\Dto\EditUserInput;
use App\Enum\Civility;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * Formulaire de modification des informations personnelles d'un compte client (civilité, nom, prénom, e-mail, téléphone).
 */
class EditUserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lastname', TextType::class, [
                'label' => false,
                'attr' => [
                    "placeholder" => 'Entrer votre nom',
                    "class" => 'form-control'
                ],
                'row_attr' => [
                    'class' => 'form-group mb-3'
                ]
            ])
            ->add('firstname', TextType::class, [
                'label' => false,
                'attr' => [
                    "placeholder" => 'Entrer votre Prénom',
                    "class" => 'form-control'
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
                'constraints' => [
                    new NotBlank(message: 'Veuillez choisir une civilité.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => false,
                'attr' => [
                    "placeholder" => 'Entrer votre e-mail',
                    "class" => 'form-control'
                ],
                'row_attr' => [
                    'class' => 'form-group mb-3'
                ]
            ])
            ->add('phone', TextType::class, [
                'required' => false,
                'label' => false,
                'attr' => [
                    "placeholder" => 'Entrer votre numéro de téléphone (optionnelle)',
                    "class" => 'form-control'
                ],
                'row_attr' => [
                    'class' => 'form-group mb-3'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => EditUserInput::class,
        ]);
    }
}
