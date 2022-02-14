<?php

namespace NetBS\CoreBundle\Service;

use NetBS\CoreBundle\Model\ConfigurableExporterInterface;
use NetBS\CoreBundle\Model\ExporterInterface;

class ExporterManager
{
    /**
     * @var ListBridgeManager
     */
    protected $bridges;

    public function __construct(ListBridgeManager $bridgeManager)
    {
        $this->bridges  = $bridgeManager;
    }

    /**
     * @var ExporterInterface[]
     */
    protected $exporters    = [];

    public function registerExporter(ExporterInterface $exporter) {

        $this->exporters[]  = $exporter;
    }

    /**
     * @return ExporterInterface[]
     */
    public function getExporters() {

        return $this->exporters;
    }

    /**
     * @param $class
     * @return ExporterInterface[]
     */
    public function getExportersForClass($class) {

        $exporters  = [];

        foreach($this->exporters as $exporter)
            if($this->bridges->isValidTransformation($class, $exporter->getExportableClass()))
                $exporters[] = $exporter;

        return $exporters;
    }

    /**
     * @param $alias
     * @return ExporterInterface
     * @throws \Exception
     */
    public function getExporterByAlias($alias) {

        foreach($this->exporters as $exporter)
            if($exporter->getAlias() === $alias)
                return $exporter;

        throw new \Exception("Invalid exporter alias");
    }
}
