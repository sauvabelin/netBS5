<?php

namespace App\Form\Search;

use NetBS\CoreBundle\Form\Type\SwitchType;
use Symfony\Component\Form\AbstractType;

class SearchNoAPMBSType extends AbstractType
{
    public function getParent()
    {
        return SwitchType::class;
    }
}
