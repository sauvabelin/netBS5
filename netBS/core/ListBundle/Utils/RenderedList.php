<?php

namespace NetBS\ListBundle\Utils;

use NetBS\ListBundle\Model\ListModelInterface;
use NetBS\ListBundle\Model\RendererInterface;
use Symfony\Component\Stopwatch\StopwatchEvent;

class RenderedList
{
    protected $list;

    protected $time;

    protected $renderer;

    public function __construct(ListModelInterface $listModel, RendererInterface $renderer, StopwatchEvent $stopwatchEvent)
    {
        $this->list     = $listModel;
        $this->renderer = $renderer;
        $this->time     = $stopwatchEvent;
    }

    public function getList() {

        return $this->list;
    }

    public function getTimeEvent() {

        return $this->time;
    }

    public function toArray() {

        $renderer   = $this->renderer;

        return [

            'renderer'  => $renderer->getName(),
            'list'      => ListUtils::toArray($this->list),
            'time'      => $this->time->getDuration()
        ];
    }
}