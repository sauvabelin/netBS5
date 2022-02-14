<?php

namespace NetBS\CoreBundle\Block\Model;

class Tab
{
    protected $title;

    protected $template;

    protected $params;

    /**
     * @param string $title
     * @param string $template
     * @param array  $params
     */
    public function __construct($title, $template, array $params = [])
    {
        $this->title    = $title;
        $this->template = $template;
        $this->params   = $params;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
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