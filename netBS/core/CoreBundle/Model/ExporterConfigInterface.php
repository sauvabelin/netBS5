<?php

namespace NetBS\CoreBundle\Model;

interface ExporterConfigInterface
{
    /**
     * @return string
     */
    public static function getName();

    /**
     * @return string|null
     */
    public static function getDescription();
}
