<?php

namespace NetBS\CoreBundle\Utils\Traits;

use Symfony\Bundle\FrameworkBundle\Routing\Router;

trait RouterTrait
{
    /**
     * @var Router
     */
    protected $router;

    public function setRouter(Router $router) {

        $this->router   = $router;
    }
}
