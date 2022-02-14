<?php

namespace Ovesco\FacturationBundle\Form;

use Ovesco\FacturationBundle\Model\MassCreances;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MassCreanceType extends BaseCreanceType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('selectedIds', HiddenType::class)
            ->add('itemsClass', HiddenType::class)
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => MassCreances::class
        ));
    }
}
