<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class)
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Admin' => 'ROLE_ADMIN',
                    'Staff' => 'ROLE_STAFF',
                    'User'  => 'ROLE_USER',
                ],
                'multiple' => false,
                'expanded' => false,
            ])
            ->add('password', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Password'
            ]);

        // Transform single string <-> array
        $builder->get('roles')
            ->addModelTransformer(new CallbackTransformer(
                fn ($rolesArray) => $rolesArray[0] ?? null,  // array -> string
                fn ($roleString) => [$roleString]           // string -> array
            ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
