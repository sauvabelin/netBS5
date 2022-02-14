<?php

namespace NetBS\FichierBundle\Form\Export;

use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\FichierBundle\Exporter\Config\CSVMembreConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CSVMembreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('adresse', SwitchType::class, array('label' => "Adresse"))
            ->add('telephone', SwitchType::class, array('label' => "Téléphone"))
            ->add('email', SwitchType::class, array('label' => "Email"))
            ->add('unite', SwitchType::class, array('label' => "Unité"))
            ->add('fonction', SwitchType::class, array('label' => "Fonction"))
            ->add('sexe', SwitchType::class, array('label' => "Sexe"))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('data_class', CSVMembreConfig::class);
    }
}