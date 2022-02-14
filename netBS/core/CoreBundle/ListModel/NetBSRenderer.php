<?php

namespace NetBS\CoreBundle\ListModel;

use NetBS\CoreBundle\Event\NetbsRendererToolbarEvent;
use NetBS\CoreBundle\ListModel\Renderer\Toolbar;
use NetBS\ListBundle\Model\RendererInterface;
use NetBS\ListBundle\Model\SnapshotTable;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

class NetBSRenderer implements RendererInterface
{
    protected $engine;

    protected $dispatcher;

    public function __construct(Environment $engine, EventDispatcherInterface $dispatcher)
    {
        $this->engine           = $engine;
        $this->dispatcher       = $dispatcher;
    }

    /**
     * Returns this renderer's name
     * @return string
     */
    public function getName()
    {
        return 'netbs';
    }

    /**
     * Renders the given prototype table
     * @param SnapshotTable $table
     * @return string
     * @throws \Exception
     */
    public function render(SnapshotTable $table, $params = [])
    {
        $toolbar    = new Toolbar();
        $tableId    = uniqid("__dt_");
        $event      = new NetbsRendererToolbarEvent($toolbar, $table, $tableId);

        $this->dispatcher->dispatch($event, NetbsRendererToolbarEvent::NAME);

        return $this->engine->render('@NetBSCore/renderer/netbs.renderer.twig', array(
            'table'     => $table,
            'tableId'   => $tableId,
            'toolbar'   => $toolbar,
            'params'    => $params,
        ));
    }
}
