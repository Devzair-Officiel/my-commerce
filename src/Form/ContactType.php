<?php

namespace App\Form;

use App\Entity\Contact;
use Karser\Recaptcha3Bundle\Form\Recaptcha3Type;
use Karser\Recaptcha3Bundle\Validator\Constraints\Recaptcha3;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;

/**
 * Formulaire de contact public avec protection reCAPTCHA v3 et validation côté serveur.
 */
class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'mapped'   => false,
                'required' => false,
                'label'    => false,
                'attr'     => [
                    'placeholder' => 'Votre nom',
                    'class'       => 'form-control',
                    'autocomplete' => 'name',
                ],
                'row_attr' => ['class' => 'nide-contact__field'],
            ])
            ->add('email', \Symfony\Component\Form\Extension\Core\Type\EmailType::class, [
                'label'    => false,
                'attr'     => [
                    'placeholder'  => 'Votre adresse e-mail',
                    'class'        => 'form-control',
                    'autocomplete' => 'email',
                ],
                'row_attr' => ['class' => 'nide-contact__field'],
            ])
            ->add('subject', ChoiceType::class, [
                'label'       => false,
                'placeholder' => 'Sujet de la demande',
                'choices'     => [
                    'Question sur un miel'            => 'Question sur un miel',
                    'Conseils pour choisir un miel'   => 'Conseils pour choisir un miel',
                    'Question sur une commande'       => 'Question sur une commande',
                    'Livraison'                       => 'Livraison',
                    'Analyses et traçabilité'         => 'Analyses et traçabilité',
                    'Professionnels / partenariat'    => 'Professionnels / partenariat',
                    'Autre'                           => 'Autre',
                ],
                'attr'     => ['class' => 'form-control'],
                'row_attr' => ['class' => 'nide-contact__field'],
            ])
            ->add('content', TextareaType::class, [
                'label'    => false,
                'attr'     => [
                    'placeholder' => 'Votre message',
                    'class'       => 'form-control',
                    'rows'        => 6,
                ],
                'row_attr' => ['class' => 'nide-contact__field'],
            ])
            ->add('consent', CheckboxType::class, [
                'mapped'      => false,
                'required'    => true,
                'label'       => 'J\'accepte que mes données soient utilisées pour le traitement de ma demande.',
                'constraints' => [new IsTrue(message: 'Vous devez accepter l\'utilisation de vos données pour envoyer votre message.')],
                'row_attr'    => ['class' => 'nide-contact__field nide-contact__field--consent'],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Envoyer ma demande',
                'attr'  => ['class' => 'btn btn-fill-out btn-block'],
            ])
            ->add('captcha', Recaptcha3Type::class, [
                'constraints' => new Recaptcha3(),
                'action_name' => 'contact',
                'locale'      => 'fr',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Contact::class]);
    }
}
