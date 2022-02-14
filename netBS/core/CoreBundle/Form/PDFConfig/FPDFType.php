<?php

namespace NetBS\CoreBundle\Form\PDFConfig;

use NetBS\CoreBundle\Exporter\Config\FPDFConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FPDFType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('margeGauche', NumberType::class, array('label' => 'Marge à gauche'))
            ->add('margeHaut', NumberType::class, array('label' => 'Marge en haut'))
            ->add('interligne', NumberType::class, array('label' => "Espace interligne"))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('data_class', FPDFConfig::class);
    }
}