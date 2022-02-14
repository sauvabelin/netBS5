<?php

namespace NetBS\CoreBundle\Menu;

class Category
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var FirstLevelLink[]
     */
    protected $links    = [];

    /**
     * @var int
     */
    protected $weight   = 0;

    public function __construct($name, $weight = 0)
    {
        $this->name     = $name;
        $this->weight   = $weight;
    }

    public function addLink($key, $name, $icon, $route, array $routeParams = []) {

        $link = $this->addSubMenu($key, $name, $icon);
        $link->setRoute($route, $routeParams);

        return $this;
    }

    public function addSubMenu($key, $name, $icon) {

        $this->links[$key]  = new FirstLevelLink($key, $name, $icon);
        return $this->links[$key];
    }

    public function isEmpty() {

        foreach($this->links as $link)
            if(!$link->isEmpty())
                return false;

        return true;
    }

    /**
     * @param string $name
     * @return Category
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return FirstLevelLink[]
     */
    public function getLinks()
    {
        $links = $this->links;

        usort($links, function(FirstLevelLink $l1, FirstLevelLink $l2) {
            if($l1->getWeight() == $l2->getWeight())
                return 0;

            return $l1->getWeight() > $l2->getWeight() ? 1 : -1;
        });

        return $links;
    }

    public function getLink($key) {

        return $this->links[$key];
    }

    /**
     * @param int $weight
     * @return Category
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
}