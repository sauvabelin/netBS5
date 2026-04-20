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

        $toolbar = new Toolbar();
        $tableId = uniqid("__dt_");

        // Reuses the netbs toolbar event so toolbar subscribers stay shared across renderers.
        $event = new NetbsRendererToolbarEvent($toolbar, $table, $tableId);
        $this->dispatcher->dispatch($event, NetbsRendererToolbarEvent::NAME);

        $initialAmount = 10;
        $model->_setAjaxParams(0, $initialAmount, null);
        $totalItems = $model->countFilteredItems();
        $snapshot   = $this->listEngine->generateSnaphot($model);
        $rows       = $this->buildRowsWithIds($snapshot, $model);

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
            'allIds'      => $model->retrieveAllIds(),
            'listId'      => $model->getAlias(),
            'modelParams' => $model->getParameters(),
            'hasSearch'   => count($model->searchTerms()) > 0,
        ));
    }

    private function buildRowsWithIds(SnapshotTable $snapshot, AjaxModel $model): array
    {
        $elements = $model->getElements();
        $data     = $snapshot->getData();

        $rows = [];
        for ($i = 0, $n = count($data); $i < $n; $i++) {
            $rows[] = [
                'id'    => $elements[$i]->getId(),
                'cells' => $data[$i],
            ];
        }
        return $rows;
    }
}
