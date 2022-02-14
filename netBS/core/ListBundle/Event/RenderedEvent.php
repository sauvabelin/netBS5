<?php

namespace NetBS\ListBundle\Event;

use NetBS\ListBundle\Model\ListModelInterface;
use NetBS\ListBundle\Model\RendererInterface;
use Symfony\Contracts\EventDispatcher\Event;

abstract class RenderedEvent extends Event
{
    protected $model;

    protected $renderer;

    public function __construct(ListModelInterface $model, RendererInterface $renderer)
    {
        $this->model    = $model;
        $this->renderer = $renderer;
    }

    public function getListModel() {

        return $this->model;
    }

    public function getRenderer() {

        return $this->renderer;
    }
}
