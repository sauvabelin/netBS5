<?php

namespace NetBS\CoreBundle\Form\Type;

use Symfony\Component\OptionsResolver\OptionsResolver;

class TelephoneMaskType extends MaskType
{
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setDefault('mask', "'mask' : '999/999.99.99'");
    }

    public function getParent()
    {
        return MaskType::class;
    }
}