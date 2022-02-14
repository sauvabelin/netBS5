<?php

namespace NetBS\CoreBundle\Model;

use Symfony\Component\HttpFoundation\Response;

interface PreviewerInterface
{
    /**
     * @param $items
     * @param ExporterInterface $exporter
     * @return Response
     */
    public function preview($items, ExporterInterface $exporter);
}