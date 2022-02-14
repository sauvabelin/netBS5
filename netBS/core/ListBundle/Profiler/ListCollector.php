<?php

namespace NetBS\ListBundle\Profiler;

use NetBS\ListBundle\Service\ListEngine;
use NetBS\ListBundle\Service\ListManager;
use NetBS\ListBundle\Service\RendererManager;
use NetBS\ListBundle\Utils\ListUtils;
use NetBS\ListBundle\Utils\RendererUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class ListCollector extends DataCollector
{
    protected $listManager;

    protected $rendererManager;

    protected $engine;

    public function __construct(ListManager $listManager, RendererManager $rendererManager, ListEngine $engine)
    {
        $this->listManager      = $listManager;
        $this->rendererManager  = $rendererManager;
        $this->engine           = $engine;
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $this->data = [
            'registeredListModels'  => $this->getRegisteredListModels(),
            'registeredRenderers'   => $this->getRegisteredRenderers(),
            'renderedListModels'    => $this->getRenderedLists()
        ];
    }

    public function getData() {

        return $this->data;
    }

    public function getName()
    {
        return 'netbs.list.collector';
    }

    protected function getRenderedLists() {

        $rendered   = [];

        foreach($this->engine->getRenderedLists() as $list)
            $rendered[] = $list->toArray();

        return $rendered;
    }

    protected function getRegisteredRenderers() {

        $renderers  = [];

        foreach($this->rendererManager->getRegisteredRenderers() as $id => $renderer)
            $renderers[$id] = RendererUtils::toArray($renderer);

        return $renderers;
    }

    protected function getRegisteredListModels() {

        $models  = [];

        foreach($this->listManager->getRegisteredModels() as $id => $listModel)
            $models[$id] = ListUtils::toArray($listModel);

        return $models;
    }

    public function reset()
    {
    }
}
