<?php

namespace App\Form;

use NetBS\CoreBundle\Form\Type\SwitchType;
use App\Model\AdminChangePassword;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AdminChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('password', RepeatedType::class, [
                'type'              => PasswordType::class,
                'invalid_message'   => "Les mots de passe ne sont pas identiques",
                "first_options"     => ['label' => "Nouveau mot de passe"],
                "second_options"    => ['label' => "Répéter"]
            ])
            ->add('forceChange', SwitchType::class, [
                'label' => "Forcer à changer à la prochaine connexion"
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => AdminChangePassword::class
        ));
    }
}
