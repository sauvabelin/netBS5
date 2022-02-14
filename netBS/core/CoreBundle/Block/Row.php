<?php

namespace NetBS\CoreBundle\Block;

class Row
{
    /**
     * @var Column[]
     */
    protected $columns = [];

    /**
     * @var Column
     */
    protected $parent;

    public function __construct(Column $parent = null)
    {
        $this->parent   = $parent;
    }

    /**
     * @param $lg
     * @param null $md
     * @param null $sm
     * @param null $xs
     * @return Column
     */
    public function pushColumn($lg, $md = null, $sm = null, $xs = null) {

        return $this->addColumn(count($this->columns), $lg, $md, $sm, $xs);
    }

    /**
     * @param $lg
     * @param null $md
     * @param null $sm
     * @param null $xs
     * @return Column
     */
    public function unshiftColumn($lg, $md = null, $sm = null, $xs = null) {

        return $this->addColumn(0, $lg, $md, $sm, $xs);
    }

    /**
     * Adds a column at the end
     * @param int $pos  Position of column, if too big
     * @param int $lg
     * @param null $md
     * @param null $sm
     * @param null $xs
     * @return Column
     */
    public function addColumn($pos, $lg, $md = null, $sm = null, $xs = null) {

        $md = $md ? $md : $lg;
        $sm = $sm ? $sm : $md;
        $xs = $xs ? $xs : $sm;

        $column = new Column($this, $lg, $md, $sm, $xs);

        if($pos >= count($this->columns))
            $this->columns[] = $column;

        else
            array_splice($this->columns, $pos, 0, [$column]);

        return $column;
    }

    public function getColumns() {

        return $this->columns;
    }

    /**
     * @param $i
     * @return Column
     * @throws \Exception
     */
    public function getColumn($i) {
        if(isset($this->columns[$i])) return $this->columns[$i];
        throw new \Exception("No column exist at index $i");
    }

    /**
     * @return Column
     */
    public function close() {

        return $this->parent;
    }
}