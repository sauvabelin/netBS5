<?php

namespace NetBS\ListBundle\Service;

use NetBS\ListBundle\Exceptions\RendererNotFoundException;
use NetBS\ListBundle\Model\RendererInterface;

class RendererManager
{
    /**
     * @var RendererInterface[]
     */
    protected $registeredRenderers = [];

    public function registerRenderer($id, RendererInterface $registeredRenderer) {

        $this->registeredRenderers[$id] = $registeredRenderer;
    }

    public function getRegisteredRenderers() {

        return $this->registeredRenderers;
    }

    public function getRendererByName($name) {

        foreach($this->registeredRenderers as $registeredRenderer)
            if($registeredRenderer->getName() == $name)
                return $registeredRenderer;

        throw new RendererNotFoundException($name);
    }
}