<?php

namespace NetBS\CoreBundle\Menu;

class SecondLevel
{
    /**
     * @var SubLink[]
     */
    protected $subLinks = [];

    /**
     * @var string
     */
    protected $label;

    /**
     * @var int
     */
    protected $weight;

    /**
     * @var array
     */
    protected $attributes   = [];

    public function __construct($label)
    {
        $this->label    = $label;
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
     * @return array
     */
    public function getSubLinks()
    {
        return $this->subLinks;
    }

    /**
     * @param int $weight
     * @return SecondLevel
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

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel($label)
    {
        $this->label = $label;
    }

}