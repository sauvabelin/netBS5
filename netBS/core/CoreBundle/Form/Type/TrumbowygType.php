<?php

namespace NetBS\CoreBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class TrumbowygType extends AbstractType
{
    public function getParent()
    {
        return TextareaType::class;
    }
}