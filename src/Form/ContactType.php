<?php

namespace App\Form;

use App\Entity\User;
use App\Entity\Contact;
use Symfony\Component\Form\AbstractType;
use Karser\Recaptcha3Bundle\Form\Recaptcha3Type;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Karser\Recaptcha3Bundle\Validator\Constraints\Recaptcha3;

class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
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
            ->add('subject', ChoiceType::class, [
                'choices'  => [
                    'Demande de renseignements sur un produit' => 'Demande de renseignements sur un produit',
                    'Suivi de commande' => 'Suivi de commande',
                    'Problème avec une commande reçue' => 'Problème avec une commande reçue',
                    'Paiement et facturation' => 'Paiement et facturation',
                    'Retour ou échange de produit ' => 'Retour ou échange de produit ',
                    'Compte client' => 'Compte client',
                    'Autres demandes' => 'Autres demandes'
                ],
                'placeholder' => '--- Sélectionner l\'Objet de votre demande ---',
                'label' => false,
                'attr' => [
                    "class" => 'form-control'
                ],
                'row_attr' => [
                    'class' => 'form-group mb-3'
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => false,
                'attr' => [
                    "placeholder" => 'Ecriver votre message',
                    "class" => 'form-control',
                    "rows" => 6
                ],
                'row_attr' => [
                    'class' => 'form-group mb-3'
                ]
            ])
            ->add('submit', SubmitType::class, [
                'attr' => [
                    'class' => 'btn btn-fill-out btn-block mt-4'
                ],
                'label' => 'Envoyer'
            ])
            ->add('captcha', Recaptcha3Type::class, [
                'constraints' => new Recaptcha3(),
                'action_name' => 'contact',
                'locale' => 'fr',
            ]);;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Contact::class,
        ]);
    }
}
