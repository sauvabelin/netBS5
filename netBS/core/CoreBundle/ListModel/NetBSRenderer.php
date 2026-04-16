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
        $allRows = [];
        $elements = $table->getItems();
        $data = $table->getData();
        for ($i = 0; $i < count($data); $i++) {
            $allRows[] = [
                'id' => $elements[$i]->getId(),
                'cells' => $data[$i],
            ];
        }

        $elements = is_array($elements) ? $elements : iterator_to_array($elements);
        $allIds = array_map(fn($el) => $el->getId(), $elements);

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
