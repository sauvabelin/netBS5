<?php

namespace NetBS\FichierBundle\Exporter\Config;


use NetBS\CoreBundle\Model\ExporterConfigInterface;

class EtiquettesV2Config implements ExporterConfigInterface
{
    public $reperes = false;

    public $horizontalMargin = 12.5;

    public $verticalMargin = 12.5;

    public $rows = 8;

    public $columns = 4;

    public $paddingLeft = 5;

    public $paddingTop = 5;

    public $fontSize = 8;

    public $interligne = 4;

    public $economies = '';

    public $infoPage = false;

    public $mergeFamilles = true;

    public $mergeOption = 1;

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
