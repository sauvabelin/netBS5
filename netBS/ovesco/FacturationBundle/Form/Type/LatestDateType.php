<?php

namespace Ovesco\FacturationBundle\Form\Type;

use NetBS\CoreBundle\Form\Type\DatepickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LatestDateType extends AbstractType
{
    public function getParent(): ?string
    {
        return DatepickerType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('property');
        $resolver->setDefault('required', false);
    }
}
