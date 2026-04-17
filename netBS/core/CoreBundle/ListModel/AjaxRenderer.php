<?php

namespace NetBS\CoreBundle\ListModel;

use NetBS\CoreBundle\Event\NetbsRendererToolbarEvent;
use NetBS\CoreBundle\ListModel\Renderer\Toolbar;
use NetBS\ListBundle\Model\RendererInterface;
use NetBS\ListBundle\Model\SnapshotTable;
use NetBS\ListBundle\Service\ListEngine;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Twig\Environment;

class AjaxRenderer implements RendererInterface
{
    protected $engine;

    protected $dispatcher;

    protected $listEngine;

    public function __construct(Environment $engine, EventDispatcherInterface $dispatcher, ListEngine $listEngine)
    {
        $this->engine           = $engine;
        $this->dispatcher       = $dispatcher;
        $this->listEngine       = $listEngine;
    }

    /**
     * Returns this renderer's name
     * @return string
     */
    public function getName()
    {
        return 'ajax';
    }

    /**
     * Renders the given prototype table
     * @param SnapshotTable $table
     * @return string
     * @throws \Exception
     */
    public function render(SnapshotTable $table, $params = [])
    {
        /** @var AjaxModel $model */
        $model = $table->getModel();
        if (!$model instanceof AjaxModel) {
            throw new \Exception("Table {$model->getAlias()} must extend AjaxModel");
        }

        $toolbar    = new Toolbar();
        $tableId    = uniqid("__dt_");

        // Make it compatible with netbs toolbar
        $event      = new NetbsRendererToolbarEvent($toolbar, $table, $tableId);

        $this->dispatcher->dispatch($event, NetbsRendererToolbarEvent::NAME);

        // Generate server-side data for the first page
        $initialAmount = 10;
        $model->_setAjaxParams(0, $initialAmount, null);
        $totalItems = $model->countFilteredItems();
        $snapshot = $this->listEngine->generateSnaphot($model);

        // Build row data with IDs
        $rows = [];
        for ($i = 0; $i < count($snapshot->getData()); $i++) {
            $row = $snapshot->getData()[$i];
            $item = $model->getElements()[$i];
            $rows[] = [
                'id' => $item->getId(),
                'cells' => $row,
            ];
        }

        $allIds = $model->retrieveAllIds();

        return $this->engine->render('@NetBSCore/renderer/ajax.renderer.twig', array(
            'table'       => $table,
            'tableId'     => $tableId,
            'toolbar'     => $toolbar,
            'params'      => $params,
            'rows'        => $rows,
            'headers'     => $snapshot->getHeaders(),
            'page'        => 0,
            'amount'      => $initialAmount,
            'search'      => '',
            'totalItems'  => $totalItems,
            'allIds'      => $allIds,
            'listId'      => $model->getAlias(),
            'modelParams' => $model->getParameters(),
            'hasSearch'   => count($model->searchTerms()) > 0,
        ));
    }
}
