<?php

namespace Ovesco\FacturationBundle\Exporter;

use NetBS\CoreBundle\Exporter\CSVColumns;
use NetBS\CoreBundle\Exporter\CSVExporter;
use Ovesco\FacturationBundle\Entity\Paiement;
use Ovesco\FacturationBundle\Exporter\Config\CSVFactureConfig;

class CSVPaiements extends CSVExporter
{
    /**
     * @var CSVFactureConfig
     */
    protected $config;

    public function configureColumns(CSVColumns $columns)
    {
        $columns
            ->addColumn('Date', function(Paiement $p) { return $p->getDate()->format('d.m.Y');})
            ->addColumn('Montant', 'montant')
            ->addColumn('Facture', 'facture.id')
            ->addColumn('Remarques', 'remarques')
        ;
    }

    /**
     * Returns an alias representing this exporter
     * @return string
     */
    public function getAlias()
    {
        return 'facturation.csv.paiements_list';
    }

    /**
     * Returns the exported item's class
     * @return string
     */
    public function getExportableClass()
    {
        return Paiement::class;
    }

    /**
     * Returns a displayable name of this exporter
     * @return string
     */
    public function getName()
    {
        return 'Exportation rapide';
    }
}
