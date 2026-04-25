<?php

namespace App\Form;

use App\Entity\WholesaleTier;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WholesaleTierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('minQtyKg', IntegerType::class, [
                'label' => 'Quantité min. (kg)',
                'attr'  => ['min' => 1, 'placeholder' => 'Ex : 5'],
            ])
            ->add('label', TextType::class, [
                'label'    => 'Libellé affiché',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex : 5 à 14 kg'],
            ])
            ->add('unitPriceCents', MoneyType::class, [
                'label'      => 'Prix unitaire (centimes)',
                'currency'   => 'EUR',
                'divisor'    => 100,
                'attr'       => ['placeholder' => 'Ex : 12.50'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => WholesaleTier::class]);
    }
}
