<?php

namespace App\Form;

use App\Entity\Product;
use App\Entity\Category;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Image;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => '📦 Product Name',
                'attr' => [
                    'placeholder' => 'Enter product name',
                    'class' => 'form-control'
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => '📝 Description',
                'attr' => [
                    'placeholder' => 'Enter product description',
                    'rows' => 5,
                    'class' => 'form-control'
                ]
            ])
            ->add('price', MoneyType::class, [
                'label' => '💰 Price',
                'currency' => 'PHP',
                'attr' => [
                    'placeholder' => '0.00',
                    'class' => 'form-control'
                ]
            ])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'category',
                'placeholder' => '📂 Select a category',
                'label' => '📁 Category',
                'required' => false,
                'attr' => ['class' => 'form-control']
            ])
            ->add('image', TextType::class, [
                'label' => '🔗 Image URL',
                'required' => false,
                'attr' => [
                    'placeholder' => 'https://example.com/image.jpg',
                    'class' => 'form-control'
                ],
                'help' => 'Or provide an image URL'
            ])
            ->add('imageFile', FileType::class, [
                'label' => '📁 Upload Image File',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/png',
                            'image/jpeg',
                            'image/jpg',
                            'image/gif',
                            'image/webp',
                            'image/avif',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file (PNG, JPEG, JPG, GIF, WEBP, AVIF)',
                        'maxSizeMessage' => 'The image file is too large ({{ size }} {{ suffix }}). Maximum allowed size is {{ limit }} {{ suffix }}.',
                    ])
                ],
                'attr' => [
                    'accept' => 'image/png,image/jpeg,image/jpg,image/gif,image/webp,image/avif',
                    'class' => 'form-control-file'
                ],
                'help' => 'Supported formats: PNG, JPEG, JPG, GIF, WEBP, AVIF (Max: 5MB)'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
