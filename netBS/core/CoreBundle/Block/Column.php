<?php

namespace NetBS\CoreBundle\Block;

class Column
{
    /**
     * @var Row[]
     */
    protected $rows = [];

    /**
     * @var Row
     */
    protected $parent;

    /**
     * @var int
     */
    protected $width;

    /**
     * @var ['class', 'params']
     */
    protected $metaBlock = null;

    public function __construct($parent, $lg, $md, $sm, $xs)
    {
        $this->parent   = $parent;
        $this->width    = ['lg' => $lg, 'md' => $md, 'sm' => $sm, 'xs' => $xs];
    }

    public function setBlock($blockClass, array $params = []) {

        if(count($this->rows) > 0)
            throw new \Exception("Can't set block in a column containing some rows");

        $this->metaBlock    = new MetaBlock($blockClass, $params);
        return $this;
    }

    public function addRow() {

        if($this->metaBlock !== null)
            throw new \Exception("Can't add rows in a column containing a block");

        $row  = new Row($this);
        $this->rows[]   = $row;
        return $row;
    }

    /**
     * @return Row[]
     */
    public function getRows() {

        return $this->rows;
    }

    /**
     * @param $i
     * @return Row
     */
    public function getRow($i) {

        if(isset($this->rows[$i]))
            return $this->rows[$i];

        return $this->addRow();
    }

    public function hasBlock() {

        return $this->metaBlock !== null;
    }

    /**
     * @return MetaBlock
     */
    public function getBlock() {

        return $this->metaBlock;
    }

    /**
     * @return Row
     */
    public function close() {

        return $this->parent;
    }

    /**
     * @return array
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getStandardWidth() {

        return $this->width['lg'];
    }
}