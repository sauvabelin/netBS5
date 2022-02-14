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

class AutorisationType extends AbstractType
{
    protected $fichierConfig;
    protected $secureConfig;

    public function __construct(FichierConfig $fichierConfig, SecureConfig $secureConfig)
    {
        $this->secureConfig     = $secureConfig;
        $this->fichierConfig    = $fichierConfig;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('user', AjaxSelect2DocumentType::class, [
                'class' => $this->secureConfig->getUserClass(),
                'label' => "Utilisateur"
            ])
            ->add('groupe', AjaxSelect2DocumentType::class, [
                'class' => $this->fichierConfig->getGroupeClass(),
                'label' => 'Groupe'
            ])
            ->add('roles', Select2DocumentType::class, [
                'class' => $this->secureConfig->getRoleClass(),
                'label' => 'Roles attribués sur le groupe',
                'multiple' => true
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefaults([
                'data_class'    => $this->secureConfig->getAutorisationClass(),
            ]);
    }
}
