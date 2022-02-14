<?php

namespace Ovesco\FacturationBundle\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CountSearchType extends NumberType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setRequired('property');
        $resolver->setDefault('required', false);
    }
}
