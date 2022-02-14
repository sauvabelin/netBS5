<?php

namespace NetBS\SecureBundle\Form;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\CoreBundle\Form\Type\Select2DocumentType;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Service\SecureConfig;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    protected $fichierConfig;

    protected $secureConfig;

    public function __construct(FichierConfig $fichierConfig, SecureConfig $secureConfig)
    {
        $this->fichierConfig    = $fichierConfig;
        $this->secureConfig     = $secureConfig;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', TextType::class, array('label' => "Nom d'utilisateur"))
            ->add('email', EmailType::class, array('label' => 'Email du compte', 'required' => false))
            ->add('membre', AjaxSelect2DocumentType::class, array(
                'class'         => $this->fichierConfig->getMembreClass(),
                'required'      => false,
                'null_option'   => true
            ));

        if($options['operation'] === CRUD::CREATE) {

            $builder->add('password', RepeatedType::class, array(
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Mot de passe'],
                'second_options' => ['label' => 'Répéter le mot de passe']
            ));
        }

        $builder
            ->add('roles', Select2DocumentType::class, array(
                'multiple'      => true,
                'class'         => $this->secureConfig->getRoleClass()
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class'    => $this->secureConfig->getUserClass(),
                'operation'     => CRUD::CREATE
            ]);
    }
}
