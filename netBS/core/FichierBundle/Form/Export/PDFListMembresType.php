<?php

namespace NetBS\FichierBundle\Form\Export;

use NetBS\FichierBundle\Exporter\Config\PDFListMembresConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PDFListMembresType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('nom', TextType::class, array('label' => 'titre', 'required' => false))
            ->add('fontSize', NumberType::class, array('label' => 'Taille de police'))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('data_class', PDFListMembresConfig::class);
    }
}