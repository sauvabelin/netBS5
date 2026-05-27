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
        if ($options['require_current']) {
            $builder->add('old_password', PasswordType::class, ['label' => 'Mot de passe actuel']);
        }

        $builder->add('new_password', RepeatedType::class, [
            'type'            => PasswordType::class,
            'invalid_message' => 'Les mots de passe ne sont pas identiques',
            'first_options'   => ['label' => 'Nouveau mot de passe'],
            'second_options'  => ['label' => 'Répéter'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'      => ChangePassword::class,
            'require_current' => true,
        ]);

        $resolver->setAllowedTypes('require_current', 'bool');
    }
}
