<?php

namespace Ovesco\FacturationBundle\Form\Export;

use NetBS\CoreBundle\Form\Type\SwitchType;
use Ovesco\FacturationBundle\Exporter\Config\CSVFactureConfig;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CSVFacturesPayeesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('closedWithPayement', SwitchType::class, array('label' => "Payées avec le paiement"))
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('data_class', CSVFactureConfig::class);
    }
}
