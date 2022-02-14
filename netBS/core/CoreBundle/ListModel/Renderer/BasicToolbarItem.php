<?php

namespace NetBS\CoreBundle\ListModel\Renderer;

class BasicToolbarItem extends ToolbarItem
{
    protected $content;

    protected $position;

    public function __construct($content, $position = ToolbarItem::LEFT)
    {
        $this->content  = $content;
        $this->position = $position;
    }

    public function render()
    {
        return $this->content;
    }

    public function getPosition()
    {
        return $this->position;
    }
}