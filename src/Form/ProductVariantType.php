<?php

namespace App\Form;

use App\Entity\ProductVariant;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class ProductVariantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'help' => "Ex : 250g, 500ml, 1kg",
                'label' => 'Libellé',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(max: 60),
                ],
            ])
            ->add('sku', TextType::class, [
                'label' => 'SKU',
                'required' => false,
                'empty_data' => '',
                'help' => 'Laisser vide : génération automatique',
            ])
            // priceCents en int => on l’édite proprement en euros via MoneyType
            ->add('priceCents', MoneyType::class, [
                'label' => 'Prix',
                'currency' => 'EUR',
                'required' => true,
                // MoneyType attend un “amount” en unité (euros),
                // et stocke en “cents” si divisors=100
                'divisor' => 100,
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\PositiveOrZero(),
                ],
            ])
            ->add('inStock', CheckboxType::class, [
                'label' => 'En stock',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProductVariant::class,
        ]);
    }
}
