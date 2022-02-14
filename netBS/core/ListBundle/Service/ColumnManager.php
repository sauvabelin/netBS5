<?php

namespace NetBS\ListBundle\Service;


use NetBS\ListBundle\Column\BaseColumn;

class ColumnManager
{
    /**
     * @var BaseColumn
     */
    protected $columns;

    public function registerColumn(BaseColumn $column) {
        $this->columns[] = $column;
    }

    /**
     * @param $class
     * @return BaseColumn
     * @throws \Exception
     */
    public function getColumn($class) {

        foreach($this->columns as $column)
            if(get_class($column) == $class)
                return $column;

        throw new \Exception("Column with class '$class' doesn't exist!");
    }
}