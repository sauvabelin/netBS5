<?php

namespace NetBS\CoreBundle\Model;

class TogglableRow
{
    private $template;

    private $params;

    public function __construct($template, $params = [])
    {
        $this->template = $template;
        $this->params   = $params;
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }
}