<?php

namespace NetBS\ListBundle\Exceptions;

class RendererNotFoundException extends \Exception
{
    public function __construct($name)
    {
        parent::__construct("Renderer with name " . $name . " couldn't be found!");
    }
}