<?php

namespace NetBS\CoreBundle\Menu;

class MainMenu
{
    /**
     * @var Category[]
     */
    protected $categories = [];

    public function registerCategory($key, $name = null, $weight = 0) {

        $name                   = $name === null ? $key : $name;
        $category               = new Category($name, $weight);
        $this->categories[$key] = $category;

        return $category;
    }

    /**
     * @param $key
     * @return Category|null
     */
    public function getCategory($key) {

        foreach($this->categories as $k => $category)
            if($k == $key)
                return $category;

        return null;
    }

    /**
     * @return Category[]
     */
    public function getCategories() {

        $categories = [];

        foreach($this->categories as $category)
            if(count($category->getLinks()) > 0)
                $categories[] = $category;

        usort($categories, function(Category $c1, Category $c2) {
            if($c1->getWeight() == $c2->getWeight())
                return 0;

            return $c1->getWeight() < $c2->getWeight() ? 1 : -1;
        });

        return $categories;
    }
}