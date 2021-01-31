<?php

namespace App\Model;

class Markdown extends \Parsedown
{
    private $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    protected function inlineImage($Excerpt)
    {
        $result = parent::inlineImage($Excerpt);
        if (!isset($result)) return null;

        $result['element']['attributes']['src'] = $this->path . $result['element']['attributes']['src'];
    }
}
