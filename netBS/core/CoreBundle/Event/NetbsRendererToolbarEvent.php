<?php

namespace NetBS\CoreBundle\Event;

use NetBS\CoreBundle\ListModel\Renderer\Toolbar;
use NetBS\ListBundle\Model\SnapshotTable;
use Symfony\Contracts\EventDispatcher\Event;

class NetbsRendererToolbarEvent extends Event
{
    const NAME  = 'netbs.list_model.renderer.toolbar';

    /**
     * @var Toolbar
     */
    private $toolbar;

    /**
     * @var SnapshotTable
     */
    private $table;

    /**
     * @var string
     */
    private $twigTableId;

    public function __construct(Toolbar $toolbar, SnapshotTable $table, $twigTableId)
    {
        $this->toolbar      = $toolbar;
        $this->table        = $table;
        $this->twigTableId  = $twigTableId;
    }

    /**
     * @return Toolbar
     */
    public function getToolbar() {

        return $this->toolbar;
    }

    /**
     * @return SnapshotTable
     */
    public function getTable() {

        return $this->table;
    }

    /**
     * @return string
     */
    public function getTableId()
    {
        return $this->twigTableId;
    }
}
