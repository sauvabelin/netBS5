<?php

namespace NetBS\FichierBundle\Form\Search;

use NetBS\CoreBundle\Form\Type\AjaxSelect2DocumentType;
use NetBS\CoreBundle\Form\Type\DaterangeType;
use NetBS\FichierBundle\Model\Search\SearchObtentionDistinction;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchObtentionDistinctionType extends AbstractType
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('distinction', AjaxSelect2DocumentType::class, array(
                'label'     => 'Distinction',
                'class'     => $this->config->getDistinctionClass(),
                'required'  => false
            ))
            ->add('date', DaterangeType::class, array('required' => false,
                'gt_options'   => ['label' => "Obtenue après"],
                'lt_options'   => ['label' => "Obtenue avant"]
            ))
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(array(
            'data_class' => SearchObtentionDistinction::class
        ));
    }
}
