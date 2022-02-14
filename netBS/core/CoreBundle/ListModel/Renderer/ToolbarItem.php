<?php

namespace NetBS\CoreBundle\ListModel\Renderer;

abstract class ToolbarItem
{
    const   LEFT    = 'left';
    const   RIGHT   = 'right';

    /**
     * Called by the renderer to render the actual item
     * @return string
     */
    abstract public function render();

    /**
     * Returns this item's position in the toolbar, either left or right
     * @return string
     */
    public function getPosition() {

        return self::LEFT;
    }
}