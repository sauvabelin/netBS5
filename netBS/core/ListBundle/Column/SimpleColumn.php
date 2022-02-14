<?php

namespace NetBS\ListBundle\Column;

class SimpleColumn extends BaseColumn
{
    protected $accessor;

    public function getContent($item, array $params = [])
    {
        return $item;
    }
}
