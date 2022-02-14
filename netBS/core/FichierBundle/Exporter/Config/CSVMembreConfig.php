<?php

namespace NetBS\FichierBundle\Exporter\Config;

use NetBS\CoreBundle\Model\ExporterConfigInterface;

class CSVMembreConfig implements ExporterConfigInterface
{
    public $adresse = true;

    public $telephone = true;

    public $email = true;

    public $unite = true;

    public $fonction = true;

    public $sexe = true;

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
