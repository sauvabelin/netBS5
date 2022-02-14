<?php

namespace NetBS\CoreBundle\Service;

use Symfony\Component\Form\AbstractType;

class FormTypesRegistrer
{
    private $types = [];

    public function addType(AbstractType $type) {
        $this->types[] = $type;
    }

    public function getTypes() {
        return $this->types;
    }
}
