<?php

namespace NetBS\CoreBundle\Menu;

class SubLink
{
    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $route;

    /**
     * @var array
     */
    protected $routeParams = [];

    public function __construct($label, $route, array $routeParams = [])
    {
        $this->label        = $label;
        $this->route        = $route;
        $this->routeParams  = $routeParams;
    }

    /**
     * @param string $label
     * @return SubLink
     */
    public function setLabel($label)
    {
        $this->label = $label;
        return $this;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $route
     * @return SubLink
     */
    public function setRoute($route)
    {
        $this->route = $route;
        return $this;
    }

    /**
     * @return string
     */
    public function getRoute()
    {
        return $this->route;
    }

    /**
     * @return array
     */
    public function getRouteParams()
    {
        return $this->routeParams;
    }

    /**
     * @param array $routeParams
     */
    public function setRouteParams(array $routeParams)
    {
        $this->routeParams = $routeParams;
    }
}