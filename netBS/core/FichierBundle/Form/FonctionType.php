<?php

namespace NetBS\FichierBundle\Form;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\FichierBundle\Utils\Form\RemarquesUtils;
use NetBS\SecureBundle\Entity\Role;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FonctionType extends AbstractType
{
    protected $config;

    protected $secureConfig;

    public function __construct(FichierConfig $config, SecureConfig $secureConfig)
    {
        $this->config       = $config;
        $this->secureConfig = $secureConfig;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom', TextType::class, array('label' => 'Nom'))
            ->add('abbreviation', TextType::class, array('label' => 'Abbreviation'))
            ->add('poids', NumberType::class, array('label' => 'Poids'))
            ->add('roles', AjaxSelect2DocumentType::class, array(
                'label'     => 'Rôles liés',
                'class'     => $this->secureConfig->getRoleClass(),
                'multiple'  => true
            ))
        ;

        RemarquesUtils::addRemarquesField($builder);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => $this->config->getFonctionClass()
        ));
    }
}
