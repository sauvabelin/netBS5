<?php

namespace App\Model;

use NetBS\CoreBundle\Model\ExporterConfigInterface;
use NetBS\FichierBundle\Exporter\Config\EtiquettesV2Config;

class EtiquettesStammConfig extends EtiquettesV2Config implements ExporterConfigInterface
{
    public $reperes = false;

    public $horizontalMargin = 2;

    public $verticalMargin = 12.5;

    public $rows = 10;

    public $columns = 4;

    public $paddingLeft = 4;

    public $paddingTop = 3;

    public $fontSize = 9;

    public $interligne = 4;

    public $economies = '';

    public $infoPage = false;

    public $mergeFamilles = true;

    public static function getName()
    {
        return "Etiquettes ultragrip 4x10";
    }

    public static function getDescription()
    {
        return "Testé sur la HL-2250DN (marquée TN2210)";
    }
}

