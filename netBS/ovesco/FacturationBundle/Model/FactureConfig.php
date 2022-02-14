<?php

namespace Ovesco\FacturationBundle\Model;

use NetBS\CoreBundle\Exporter\Config\FPDFConfig;
use NetBS\CoreBundle\Model\ExporterConfigInterface;

class FactureConfig extends FPDFConfig implements ExporterConfigInterface
{
    public $setPrintDate = true;
    public $model = null;
    public $adresseTop = 46; // décalage haut adresse lettre
    public $adresseLeft = 130; // décalage gauche adresse lettre
    public $wg = 6; // marge gauche BVR
    public $hg = 248;// ligne codage gauche
    public $haddr = 190; // décalage hauteur adresses du haut
    public $waddr = 56; // décalage gauche adresse haut droite
    public $wccp = 77; // position X du CCP
    public $hccp = 223; // position Y du CCP
    public $wd = 114; // ligne codage droite
    public $hd = 215; // ligne codage droite
    public $wb = 70; // ligne codage bas
    public $hb = 268; // ligne codage bas
    public $bvrIl = 4;
    public $date = null;

    public function __construct($printDate = true)
    {
        $this->setPrintDate = $printDate;
        $this->margeHaut = 10;
        $this->margeGauche = 12.3;
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

