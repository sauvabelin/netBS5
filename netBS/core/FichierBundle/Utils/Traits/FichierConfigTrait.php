<?php

namespace NetBS\FichierBundle\Utils\Traits;

use NetBS\FichierBundle\Service\FichierConfig;

trait FichierConfigTrait
{
    /**
     * @var FichierConfig
     */
    protected $fichierConfig;

    public function setFichierConfig(FichierConfig $config) {

        $this->fichierConfig   = $config;
    }

    public function getFichierConfig() {

        return $this->fichierConfig;
    }
}