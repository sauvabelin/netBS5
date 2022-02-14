<?php

namespace NetBS\ListBundle\Utils;

use NetBS\ListBundle\Model\RendererInterface;

class RendererUtils
{
    public static function toArray(RendererInterface $renderer) {

        return [
            'name'      => $renderer->getName(),
            'class'     => get_class($renderer)
        ];
    }
}