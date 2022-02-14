<?php

namespace NetBS\CoreBundle\Block;

class LayoutConfigurator extends Column
{
    public function __construct()
    {
        parent::__construct(null, 0, 0, 0, 0);
    }

    public function addRow() {

        $row            = new Row($this);
        $this->rows[]   = $row;
        return $row;
    }

    public function close()
    {
        return $this;
    }
}