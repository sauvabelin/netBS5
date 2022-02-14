<?php

namespace NetBS\CoreBundle\Utils\Traits;

trait ConfigurableExporterTrait
{
    protected $configuration;

    public function setConfig($config) {
        $this->configuration = $config;
    }

    public function getConfiguration() {
        return $this->configuration;
    }
}
