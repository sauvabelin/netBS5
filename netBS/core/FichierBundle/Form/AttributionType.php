<?php

namespace NetBS\FichierBundle\Form;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\CoreBundle\Form\Type\DatepickerType;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AttributionType extends AbstractType
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
            ->add('fonction',  AjaxSelect2DocumentType::class, array(
                'label' => 'Fonction',
                'class' => $this->config->getFonctionClass()
            ))
            ->add('groupe', AjaxSelect2DocumentType::class, array(
                'label' => 'Unité',
                'class' => $this->config->getGroupeClass()
            ))
            ->add('dateDebut', DatepickerType::class, array('label' => 'Date de début'))
            ->add('dateFin', DatepickerType::class, array('label' => 'Date de fin', 'required' => false))

        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => $this->config->getAttributionClass()
        ));
    }
}
