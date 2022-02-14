<?php

namespace NetBS\FichierBundle\Form\Search;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\CoreBundle\Form\Type\DatepickerType;
use NetBS\FichierBundle\Model\Search\SearchAttribution;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchAttributionType extends AbstractType
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('fonction',  AjaxSelect2DocumentType::class, array(
                'label'     => 'Fonction',
                'class'     => $this->config->getFonctionClass(),
                'required'  => false,
                'null_option'   => true
            ))
            ->add('groupe', AjaxSelect2DocumentType::class, array(
                'label'     => 'Unité',
                'multiple'  => true,
                'class'     => $this->config->getGroupeClass(),
                'required'  => false,
            ))
            ->add('dateDebut', DatepickerType::class, array('label' => 'Commencée le', 'required' => false))
            ->add('dateFin', DatepickerType::class, array('label' => 'Terminée le', 'required' => false))
            ->add('actif', SearchActiveAttributionType::class, array('label' => 'Attribution active'))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => SearchAttribution::class
        ));
    }
}
