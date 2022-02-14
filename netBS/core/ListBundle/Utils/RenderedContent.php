<?php

namespace NetBS\ListBundle\Utils;

class RenderedContent
{
    protected $content;

    public function __construct($content)
    {
        $this->content = $content;
    }

    public function __toString()
    {
        return $this->content;
    }

    public function getContent() {

        return $this->content;
    }
}