<?php

namespace NetBS\CoreBundle\Exporter;

use NetBS\CoreBundle\Model\ExporterInterface;
use NetBS\CoreBundle\Model\PreviewerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Twig\Environment;

class CSVPreviewer implements PreviewerInterface
{
    /**
     * @var PropertyAccessor
     */
    protected $accessor;

    /**
     * @var Environment
     */
    protected $twig;

    public function __construct(PropertyAccessorInterface $access, Environment $twig)
    {
        $this->accessor = $access;
        $this->twig     = $twig;
    }

    /**
     * @param $items
     * @param ExporterInterface $exporter
     * @return Response
     * @throws \Exception
     */
    public function preview($items, ExporterInterface $exporter)
    {
        if(!$exporter instanceof CSVExporter)
            throw new \Exception("Previewable excel exporter must be an instance of ExcelExporter!");

        $items      = $exporter->filterItems($items);
        $data       = [];
        $config     = new CSVColumns();

        $exporter->configureColumns($config);

        $headers    = array_map(function($column) {
            return $column['header'];
        }, $config->getColumns());

        foreach($items as $item) {

            $row    = [];
            foreach($config->getColumns() as $column) {

                $accessor   = $column['accessor'];
                $value      = is_string($accessor) ? $this->accessor->getValue($item, $accessor) : $accessor($item);
                $row[]      = $value;
            }

            $data[] = $row;
        }

        return new Response($this->twig->render('@NetBSCore/export/csv.preview.twig', [
            'data'      => $data,
            'headers'   => $headers
        ]));
    }
}
