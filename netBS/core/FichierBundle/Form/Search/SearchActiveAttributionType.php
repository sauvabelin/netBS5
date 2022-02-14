<?php

namespace NetBS\FichierBundle\Form\Search;

use NetBS\CoreBundle\Form\Type\SwitchType;
use Symfony\Component\Form\AbstractType;

class SearchActiveAttributionType extends AbstractType
{
    public function getParent()
    {
        return SwitchType::class;
    }
}