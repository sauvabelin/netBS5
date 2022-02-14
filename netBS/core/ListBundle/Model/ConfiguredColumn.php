<?php

namespace NetBS\ListBundle\Model;

class ConfiguredColumn
{
    /**
     * @var string
     */
    private $header;

    /**
     * @var string
     */
    private $accessor;

    /**
     * @var string
     */
    private $class;

    /**
     * @var array
     */
    private $params;

    public function __construct($header, $accessor, $class, $params)
    {
        $this->header   = $header;
        $this->accessor = $accessor;
        $this->class    = $class;
        $this->params   = $params;
    }

    /**
     * @return string
     */
    public function getHeader()
    {
        return $this->header;
    }

    /**
     * @param string $header
     */
    public function setHeader($header)
    {
        $this->header = $header;
    }

    /**
     * @return string
     */
    public function getAccessor()
    {
        return $this->accessor;
    }

    /**
     * @param string $accessor
     */
    public function setAccessor($accessor)
    {
        $this->accessor = $accessor;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param string $class
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @param array $params
     */
    public function setParams($params)
    {
        $this->params = $params;
    }
}