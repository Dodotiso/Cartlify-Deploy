<?php

namespace App\Form;

use App\Entity\Stock;
use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class StockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('stock', IntegerType::class, [
                'label' => 'Stock Quantity',
                'constraints' => [
                    new NotNull(['message' => 'Stock quantity is required.']),
                    new PositiveOrZero(['message' => 'Stock quantity must be 0 or greater.'])
                ],
                'attr' => [
                    'min' => 0,
                    'placeholder' => 'Enter stock quantity'
                ]
            ])
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => function($product) {
                    return $product->getName() . ' (ID: ' . $product->getId() . ')';
                },
                'placeholder' => 'Select a product',
                'label' => 'Product',
                'constraints' => [
                    new NotNull(['message' => 'Please select a product.'])
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Stock::class,
        ]);
    }
}