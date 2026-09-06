<?php

namespace NetBS\SecureBundle\Form;

use NetBS\SecureBundle\Model\ChangePassword;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['require_current']) {
            $builder->add('old_password', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'attr'  => ['autocomplete' => 'current-password'],
            ]);
        }

        // autocomplete="new-password" stops password managers misreading the
        // "Répéter" field as a current-password prompt.
        $builder->add('new_password', RepeatedType::class, [
            'type'            => PasswordType::class,
            'invalid_message' => 'Les mots de passe ne sont pas identiques',
            'first_options'   => ['label' => 'Nouveau mot de passe', 'attr' => ['autocomplete' => 'new-password']],
            'second_options'  => ['label' => 'Répéter', 'attr' => ['autocomplete' => 'new-password']],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class'      => ChangePassword::class,
            'require_current' => true,
            // The UserPassword check on oldPassword lives in the 'current_password'
            // group; only enable it when the current-password field is collected.
            'validation_groups' => static fn (FormInterface $form) => $form->getConfig()->getOption('require_current')
                ? ['Default', 'current_password']
                : ['Default'],
        ]);

        $resolver->setAllowedTypes('require_current', 'bool');
    }
}
