<?php

namespace NetBS\FichierBundle\Form;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\CoreBundle\Form\Type\DatepickerType;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ObtentionDistinctionType extends AbstractType
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('membre', AjaxSelect2DocumentType::class, array(
                'label'     => 'Membre',
                'class'     => $this->config->getMembreClass()
            ))
            ->add('distinction', AjaxSelect2DocumentType::class, array(
                'label'     => 'Distinction',
                'class'     => $this->config->getDistinctionClass(),
                'required'  => true
                ))
            ->add('date', DatepickerType::class, array('label' => 'Date d\'obtention'))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => $this->config->getObtentionDistinctionClass()
        ));
    }
}
