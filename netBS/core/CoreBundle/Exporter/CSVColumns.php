<?php

namespace NetBS\CoreBundle\Exporter;

class CSVColumns
{
    protected $columns  = [];

    public function addColumn($header, $accessor) {

        $this->columns[] = [
            'header'    => $header,
            'accessor'  => $accessor
        ];

        return $this;
    }

    public function getColumns() {

        return $this->columns;
    }
}