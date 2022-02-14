<?php

namespace NetBS\CoreBundle\Block;

class ParamBag
{
    protected $params   = [];

    public function __construct(array $params = [])
    {
        $this->params   = $params;
    }

    public function getParams() {

        return $this->params;
    }

    public function get($key) {

        if(isset($this->params[$key]))
            return $this->params[$key];
    }

    public function set($key, $value) {

        $this->params[$key] = $value;
    }

    public function pushTo($key, $value) {

        $this->params[$key][] = $value;
    }

    public function remove($key) {

        if(isset($this->params[$key]))
            unset($this->params[$key]);
    }
}