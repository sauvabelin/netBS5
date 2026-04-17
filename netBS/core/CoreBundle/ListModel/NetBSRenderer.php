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
        $model = $table->getModel();

        $toolbar    = new Toolbar();
        $tableId    = uniqid("__dt_");
        $event      = new NetbsRendererToolbarEvent($toolbar, $table, $tableId);

        $this->dispatcher->dispatch($event, NetbsRendererToolbarEvent::NAME);

        // Build row data from the pre-built snapshot (all items)
        $elements = $table->getItems();
        $elements = is_array($elements) ? array_values($elements) : iterator_to_array($elements, false);
        $data = $table->getData();
        $allRows = [];
        $allIds = [];
        $n = count($data);
        for ($i = 0; $i < $n; $i++) {
            $id = $elements[$i]->getId();
            $allRows[] = ['id' => $id, 'cells' => $data[$i]];
            $allIds[] = $id;
        }

        // Paginate to first page
        $initialAmount = 10;
        $totalItems = count($allRows);
        $rows = array_slice($allRows, 0, $initialAmount);

        return $this->engine->render('@NetBSCore/renderer/netbs.renderer.twig', array(
            'table'       => $table,
            'tableId'     => $tableId,
            'toolbar'     => $toolbar,
            'params'      => $params,
            'rows'        => $rows,
            'headers'     => $table->getHeaders(),
            'page'        => 0,
            'amount'      => $initialAmount,
            'search'      => '',
            'totalItems'  => $totalItems,
            'allIds'      => $allIds,
            'listId'      => $model->getAlias(),
            'modelParams' => $model->getParameters(),
            'hasSearch'   => true,
        ));
    }
}
