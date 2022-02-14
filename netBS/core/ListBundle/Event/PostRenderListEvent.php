<?php

namespace NetBS\ListBundle\Event;

use NetBS\ListBundle\Model\ListModelInterface;
use NetBS\ListBundle\Model\RendererInterface;
use NetBS\ListBundle\Utils\RenderedContent;

class PostRenderListEvent extends RenderedEvent
{
    protected $content;

    public function __construct(ListModelInterface $model, RendererInterface $renderer, RenderedContent $content)
    {
        $this->content  = $content;

        parent::__construct($model, $renderer);
    }

    public function getContent() {

        return $this->content;
    }
}