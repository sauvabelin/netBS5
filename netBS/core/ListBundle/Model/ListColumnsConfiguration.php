<?php

namespace NetBS\ListBundle\Model;

use NetBS\ListBundle\Column\BaseColumn;

class ListColumnsConfiguration
{
    /**
     * @var array
     */
    protected $columns = [];
    
    /**
     * Adds a column to the list
     * @param string            $header     Column header
     * @param string|\Closure   $accessor   Targeted property, string => propertyAccessor, closure performed on item
     * @param string            $class      The column class
     * @param array             $params     Some params required by the column
     *
     * @return $this
     */
    public function addColumn($header, $accessor, $class, array $params = []) {

        $this->columns[]    = new ConfiguredColumn($header, $accessor, $class, $params);

        return $this;
    }

    /**
     * Removes a column at a given index
     * @param $index
     * @return ConfiguredColumn
     */
    public function removeColumn($index) {

        $column = $this->columns[$index];
        unset($this->columns[$index]);

        return $column;
    }

    /**
     * @return BaseColumn[]
     */
    public function getColumns() {

        return $this->columns;
    }
}