<?php

namespace App\Exporter;

use NetBS\CoreBundle\Model\ExporterInterface;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Component\HttpFoundation\Response;

class MailingChefsExporter implements ExporterInterface
{
    private $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    public function getAlias()
    {
        return 'mailing.chefs';
    }

    public function getExportableClass()
    {
        return $this->config->getMembreClass();
    }

    public function getCategory()
    {
        return 'Mailing';
    }

    public function getName()
    {
        return 'Liste Chefs';
    }

    public function export($items)
    {
        return new Response();
    }
}
