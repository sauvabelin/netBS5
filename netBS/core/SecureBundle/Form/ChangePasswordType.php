<?php

namespace NetBS\SecureBundle\Form;

use NetBS\SecureBundle\Model\ChangePassword;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('old_password', PasswordType::class, array('label' => "Mot de passe actuel"))
            ->add('new_password', RepeatedType::class, array(
                'type'              => PasswordType::class,
                'invalid_message'   => "Les mots de passe ne sont pas identiques",
                'first_options'     => ['label' => "Nouveau mot de passe"],
                'second_options'    => ['label' => "Répéter"]
            ));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'    => ChangePassword::class
        ]);
    }
}