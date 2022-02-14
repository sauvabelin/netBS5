<?php

namespace NetBS\CoreBundle\Twig\Extension;

use Doctrine\Common\Util\ClassUtils;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class HelperExtension extends AbstractExtension
{
    public function getName()
    {
        return 'helper';
    }

    public function getFunctions() {

        return [

            new TwigFunction('helper', [$this, 'generateHelperAttribute']),
        ];
    }

    public function getFilters() {

        return [];
    }

    public function generateHelperAttribute($object, $placement = 'top') {

        $class  = ClassUtils::getClass($object);

        if(!method_exists($object, 'getId'))
            throw new \Exception("Method getId doesn't exist in $class");

        $class  = base64_encode($class);
        $id     = $object->getId();

        return "data-helper data-helper-class='$class' data-helper-id=$id data-helper-placement='$placement'";
    }
}
