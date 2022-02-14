<?php

namespace Ovesco\FacturationBundle\Model;

use NetBS\CoreBundle\Exporter\Config\FPDFConfig;
use NetBS\CoreBundle\Model\ExporterConfigInterface;

class QrFactureConfig extends FPDFConfig implements ExporterConfigInterface
{
    public $border = true;

    public $adresseTop = 46; // décalage haut adresse lettre
    public $adresseLeft = 130; // décalage gauche adresse lettre

    public $model = null;

    public $date;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

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
