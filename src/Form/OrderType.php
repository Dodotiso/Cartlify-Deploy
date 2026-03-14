<?php

namespace App\Form;

use App\Entity\Order;
use App\Entity\Stock;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class OrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('stock', EntityType::class, [
                'class' => Stock::class,
                'choice_label' => function(Stock $stock) {
                    return $stock->getProduct()->getName() . ' (Qty: ' . $stock->getStock() . ')';
                },
                'placeholder' => 'Select a product',
                'label' => 'Product',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Order::class,
        ]);
    }
}
