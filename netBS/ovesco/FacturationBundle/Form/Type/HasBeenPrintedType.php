<?php

namespace Ovesco\FacturationBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HasBeenPrintedType extends AbstractType
{
    public function getParent()
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('required', false);
        $resolver->setDefault('choices', [
            'oui' => 'yes',
            'non' => 'no'
        ]);
    }
}
