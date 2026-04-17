<?php

namespace NetBS\CoreBundle\Twig\Extension;

use Doctrine\Common\Util\ClassUtils;
use Symfony\Component\Routing\RouterInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class HelperExtension extends AbstractExtension
{
    public function __construct(private RouterInterface $router)
    {
    }

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
        $url    = $this->router->generate('netbs.core.helper.get_help');

        return "data-controller=\"helper-popover\" "
            . "data-helper-popover-id-value=\"$id\" "
            . "data-helper-popover-class-value=\"$class\" "
            . "data-helper-popover-url-value=\"$url\" "
            . "data-helper-popover-placement-value=\"$placement\" "
            . "data-action=\"mouseenter->helper-popover#mouseenter mouseleave->helper-popover#mouseleave\"";
    }
}
