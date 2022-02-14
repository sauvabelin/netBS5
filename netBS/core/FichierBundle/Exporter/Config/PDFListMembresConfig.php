<?php

namespace NetBS\FichierBundle\Exporter\Config;

use NetBS\CoreBundle\Model\ExporterConfigInterface;

class PDFListMembresConfig implements ExporterConfigInterface
{
    /**
     * @var string
     */
    public $nom;

    /**
     * @var int
     */
    public $fontSize = 12;

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
