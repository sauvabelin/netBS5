<?php

namespace NetBS\CoreBundle\Profiler;

use NetBS\CoreBundle\Service\ExporterManager;
use NetBS\CoreBundle\Service\ListBridgeManager;
use NetBS\CoreBundle\Service\PreviewerManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class NetBSCollector extends DataCollector
{
    /**
     * @var ListBridgeManager
     */
    protected $bridgeManager;

    /**
     * @var ExporterManager
     */
    protected $exporterManager;

    /**
     * @var PreviewerManager
     */
    protected $previewerManager;

    public function __construct(ListBridgeManager $bridgeManager, ExporterManager $exporterManager, PreviewerManager $previewerManager)
    {
        $this->bridgeManager    = $bridgeManager;
        $this->exporterManager  = $exporterManager;
        $this->previewerManager = $previewerManager;
    }

    public function getData() {

        return $this->data;
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null)
    {
        $bridges    = [];
        $exporters  = [];
        $previewers = [];

        foreach($this->previewerManager->getPreviewers() as $previewer)
            $previewers[]   = get_class($previewer);

        foreach($this->bridgeManager->getBridges() as $bridge)
            $bridges[]  = [
                'class' => get_class($bridge),
                'from'  => $bridge->getFromClass(),
                'to'    => $bridge->getToClass(),
                'cost'  => $bridge->getCost()
            ];

        foreach($this->exporterManager->getExporters() as $exporter)
            $exporters[]    = [
                'class'     => get_class($exporter),
                'name'      => $exporter->getName(),
                'alias'     => $exporter->getAlias(),
                'itemClass' => $exporter->getExportableClass(),
                'category'  => $exporter->getCategory()
            ];

        $this->data['previewers']   = $previewers;
        $this->data['bridges']      = $bridges;
        $this->data['exporters']    = $exporters;
    }

    public function getName()
    {
        return 'netbs.core.netbs_collector';
    }

    public function reset()
    {
    }
}
