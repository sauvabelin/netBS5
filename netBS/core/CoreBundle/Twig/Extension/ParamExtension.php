<?php

namespace NetBS\CoreBundle\Twig\Extension;

use NetBS\CoreBundle\Service\ParameterManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ParamExtension extends AbstractExtension
{
    private $params;

    public function __construct(ParameterManager $manager)
    {
        $this->params   = $manager;
    }

    public function getFunctions() {

        return [
            new TwigFunction('param', [$this, 'getParameter'])
        ];
    }

    public function getParameter($namespace, $key) {

        return $this->params->getValue($namespace, $key);
    }
}
