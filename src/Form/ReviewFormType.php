<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReviewFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', IntegerType::class, [
                'label' => 'Votre note',
                'attr'  => [
                    'min'  => 1,
                    'max'  => 5,
                    'type' => 'range',
                ],
            ])
            ->add('comment', TextareaType::class, [
                'label'    => 'Votre commentaire',
                'required' => false,
                'attr'     => [
                    'rows'        => 4,
                    'maxlength'   => 1000,
                    'placeholder' => 'Partagez votre expérience...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}
