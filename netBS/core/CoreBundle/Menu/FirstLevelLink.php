<?php

namespace NetBS\CoreBundle\Menu;

class FirstLevelLink
{
    /**
     * @var string
     */
    protected $key;

    /**
     * @var string
     */
    protected $icon;

    /**
     * @var string
     */
    protected $label;

    /**
     * @var string
     */
    protected $route = null;

    /**
     * @var array
     */
    protected $routeParams = [];

    /**
     * @var array
     */
    protected $subLinks = [];

    /**
     * @var array
     */
    protected $secondLevels = [];

    /**
     * @var int
     */
    protected $weight   = 0;

    /**
     * @var array
     */
    protected $attributes = [];

    public function __construct($key, $label, $icon)
    {
        $this->key      = $key;
        $this->label    = $label;
        $this->icon     = $icon;
    }

    public function isEmpty() {

        return count($this->subLinks) === 0 && $this->route === null;
    }

    /**
     * @param $route
     * @param array $routeParams
     * @return $this
     */
    public function setRoute($route, array $routeParams = []) {

        $this->route        = $route;
        $this->routeParams  = $routeParams;
        return $this;
    }

    /**
     * @param $label
     * @param $route
     * @param array $routeParams
     * @return $this
     */
    public function addSubLink($label, $route, array $routeParams = []) {

        $this->subLinks[]   = new SubLink($label, $route, $routeParams);
        return $this;
    }

    /**
     * @param $label
     * @return SecondLevel
     */
    public function addSecondLevelMenu($label) {

        $second                 = new SecondLevel($label);
        $this->secondLevels[]   = $second;
        return $second;
    }

    /**
     * @return array
     */
    public function getSecondLevelMenus()
    {
        return $this->secondLevels;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getIcon()
    {
        return $this->icon;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
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
     * @return array
     */
    public function getSubLinks()
    {
        return $this->subLinks;
    }

    /**
     * @param int $weight
     * @return FirstLevelLink
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;
        return $this;
    }

    /**
     * @return int
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @param array $attributes
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }
}