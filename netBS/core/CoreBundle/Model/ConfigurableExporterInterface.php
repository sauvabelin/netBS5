<?php

namespace NetBS\CoreBundle\Model;

interface ConfigurableExporterInterface
{
    /**
     * Returns the form used to configure the export
     * @return string
     */
    public function getConfigFormClass();

    /**
     * Returns the configuration object class
     * @return ExporterConfigInterface|ExporterConfigInterface[]
     */
    public function getBasicConfig();

    /**
     * Sets the configuration for this export
     * @param $config
     */
    public function setConfig($config);

    /**
     * If the rendered file can be previewed, return the used
     * previewer class
     * @return string
     */
    public function getPreviewer();
}
