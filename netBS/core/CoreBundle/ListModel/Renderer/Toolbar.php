<?php

namespace NetBS\CoreBundle\ListModel\Renderer;

class Toolbar
{
    protected $items    = [];

    public function getItems($position = null) {

        if(!$position)
            return $this->items;

        return array_filter($this->items, function(ToolbarItem $item) use ($position) {
            return $item->getPosition() === $position;
        });
    }

    public function addItem(ToolbarItem $item) {

        $this->items[]  = $item;
    }
}
