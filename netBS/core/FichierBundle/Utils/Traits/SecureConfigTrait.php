<?php

namespace NetBS\FichierBundle\Utils\Traits;

use NetBS\SecureBundle\Service\SecureConfig;

trait SecureConfigTrait
{
    /**
     * @var SecureConfig
     */
    protected $config;

    public function setSecureConfig(SecureConfig $config) {

        $this->config   = $config;
    }

    public function getSecureConfig() {

        return $this->config;
    }
}