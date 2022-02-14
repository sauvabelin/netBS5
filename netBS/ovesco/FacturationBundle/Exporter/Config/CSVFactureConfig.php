<?php

namespace Ovesco\FacturationBundle\Exporter\Config;

use NetBS\CoreBundle\Model\ExporterConfigInterface;

class CSVFactureConfig implements ExporterConfigInterface
{
    public $closedWithPayement = true;

    /**
     * @return string
     */
    public static function getName()
    {
        return "Par défaut";
    }

    /**
     * @return string|null
     */
    public static function getDescription()
    {
        return null;
    }
}
