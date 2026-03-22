<?php

namespace App\Form;

use App\Entity\Stock;
use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

class StockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('stock', IntegerType::class, [
                'label' => 'Stock Quantity'
            ])
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => 'name',
                'placeholder' => 'Select a product',
                'label' => 'Product',
            ]);
            // ->add('image', TextType::class, [
            //     'label' => 'Image URL',
            //     'required' => false,
            // ])
            // ->add('createdAt', DateTimeType::class, [
            //     'label' => 'Created At',
            //     'widget' => 'single_text',
            //     'required' => false,
            // ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Stock::class,
        ]);
    }
}
