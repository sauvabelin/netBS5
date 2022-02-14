<?php

namespace NetBS\CoreBundle\Exporter;

use NetBS\CoreBundle\Model\ExporterInterface;
use NetBS\CoreBundle\Model\PreviewerInterface;
use Symfony\Component\HttpFoundation\Response;

class PDFPreviewer implements PreviewerInterface
{
    /**
     * @param $items
     * @param ExporterInterface $exporter
     * @return Response
     */
    public function preview($items, ExporterInterface $exporter)
    {
        return $exporter->export($items);
    }
}