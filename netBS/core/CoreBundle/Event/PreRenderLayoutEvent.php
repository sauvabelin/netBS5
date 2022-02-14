<?php

namespace NetBS\CoreBundle\Event;

use NetBS\CoreBundle\Block\LayoutConfigurator;
use NetBS\CoreBundle\Block\LayoutInterface;
use Symfony\Contracts\EventDispatcher\Event;

class PreRenderLayoutEvent extends Event
{
    const NAME  = "netbs.block.pre_render_layout";

    protected $layout;

    protected $configurator;

    protected $parameters;

    public function __construct(LayoutInterface $layout, LayoutConfigurator $configurator, array $parameters = []) {

        $this->layout       = $layout;
        $this->configurator = $configurator;
        $this->parameters   = $parameters;
    }

    /**
     * @return LayoutInterface
     */
    public function getLayout()
    {
        return $this->layout;
    }

    /**
     * @return LayoutConfigurator
     */
    public function getConfigurator()
    {
        return $this->configurator;
    }

    /**
     * @return array
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    public function getParameter($key) {
        return isset($this->parameters[$key]) ? $this->parameters[$key] : null;
    }
}
