<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Form;

use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;

/** First login: the user's own password, and optionally an email to reset it later. */
class FirstLoginType extends ChangePasswordType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder->add('email', EmailType::class, [
            'label'       => 'Adresse email (facultatif)',
            'help'        => 'Pour pouvoir réinitialiser ton mot de passe toi-même si tu l\'oublies.',
            'mapped'      => false,
            'required'    => false,
            'data'        => $options['current_email'],
            'constraints' => [new Email()],
            'attr'        => ['autocomplete' => 'email'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'require_current' => false,
            'current_email'   => null,
        ]);
        $resolver->setAllowedTypes('current_email', ['null', 'string']);
    }
}
