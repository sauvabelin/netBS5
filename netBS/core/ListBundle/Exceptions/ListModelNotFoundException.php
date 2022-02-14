<?php

namespace NetBS\ListBundle\Exceptions;

class ListModelNotFoundException extends \Exception
{
    /**
     * ListModelNotFoundException constructor.
     * @param string $id
     * @param string $property
     */
    public function __construct($id, $property)
    {
        parent::__construct("List model with $property " . $id . " couldn't be found!");
    }
}