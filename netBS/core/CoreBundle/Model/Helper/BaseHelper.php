<?php

namespace NetBS\CoreBundle\Model\Helper;

use Symfony\Component\Routing\Router;
use Twig\Environment;

abstract class BaseHelper implements HelperInterface
{
    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var Router
     */
    protected $router;

    public function setTwig(Environment $twig_Environment) {

        $this->twig = $twig_Environment;
    }

    public function setRouter(Router $router) {

        $this->router   = $router;
    }
}
