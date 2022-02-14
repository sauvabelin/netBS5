<?php

namespace NetBS\ListBundle\Twig;

use NetBS\ListBundle\Service\ListEngine;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ListExtension extends AbstractExtension
{
    protected $engine;

    public function __construct(ListEngine $engine)
    {
        $this->engine = $engine;
    }

    public function getFunctions() {

        return [
            new TwigFunction('render_list', array($this, 'renderListFunction'), array('is_safe' => array('html')))
        ];
    }

    public function renderListFunction($list, $renderer, array $params = []) {

        return $this->engine->render($list, $renderer, $params)->getContent();
    }
}
