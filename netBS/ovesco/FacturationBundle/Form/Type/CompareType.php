<?php

namespace Ovesco\FacturationBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CompareType extends AbstractType
{
    public function getParent()
    {
        return NumberType::class;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('property');
        $resolver->setRequired('function');
        $resolver->setDefault('required', false);
    }
}
