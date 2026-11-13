<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class)
            ->add('plainPassword', PasswordType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Password',
                'attr' => [
                    'autocomplete' => 'new-password',
                    'placeholder' => 'Enter a password',
                ],
            ])
            ->add('firstName', TextType::class)
            ->add('lastName', TextType::class)
            ->add('phone', TelType::class, [
                'required' => false,
            ])
            ->add('address', TextareaType::class, [
                'required' => false,
            ])
            ->add('isActive', CheckboxType::class, [
                'required' => false,
            ]);

        // Only allow Customer or Staff.
        // Never allow ROLE_ADMIN to be assigned through this form.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $user = $event->getData();

            $role = 'ROLE_USER';

            if ($user instanceof User && in_array('ROLE_STAFF', $user->getRoles(), true)) {
                $role = 'ROLE_STAFF';
            }

            $event->getForm()->add('accountRole', ChoiceType::class, [
                'mapped' => false,
                'choices' => [
                    'Customer' => 'ROLE_USER',
                    'Staff' => 'ROLE_STAFF',
                ],
                'data' => $role,
                'label' => 'Account Role',
            ]);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
