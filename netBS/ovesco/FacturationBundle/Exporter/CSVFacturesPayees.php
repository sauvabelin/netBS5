<?php

namespace Ovesco\FacturationBundle\Exporter;

use NetBS\CoreBundle\Exporter\CSVColumns;
use NetBS\CoreBundle\Exporter\CSVExporter;
use NetBS\CoreBundle\Exporter\CSVPreviewer;
use NetBS\CoreBundle\Model\ConfigurableExporterInterface;
use NetBS\CoreBundle\Model\ExporterConfigInterface;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\Paiement;
use Ovesco\FacturationBundle\Exporter\Config\CSVFactureConfig;
use Ovesco\FacturationBundle\Form\Export\CSVFacturesPayeesType;

class CSVFacturesPayees extends CSVExporter implements ConfigurableExporterInterface
{
    /**
     * @var CSVFactureConfig
     */
    protected $config;

    /**
     * @param Paiement[] $items
     * @return array
     */
    public function filterItems($items)
    {
        if ($this->config->closedWithPayement) {
            $items = array_filter($items, function (Paiement $paiement) {
                if ($paiement->getFacture()->getStatut() !== Facture::PAYEE) return false;
                $facture = $paiement->getFacture();
                $allPaiements = $facture->getPaiements()->toArray();
                if (count($allPaiements) === 1) return true;
                usort($allPaiements, function (Paiement $a, Paiement $b) {
                    return $a->getDate() > $b->getDate() ? 1 : -1;
                });
                $sommeInitiale = 0;
                /** @var Paiement $loopPaiement */
                foreach ($allPaiements as $loopPaiement) {
                    $sommeInitiale += $loopPaiement->getMontant();
                    if ($sommeInitiale >= $facture->getMontant()) {
                        return $loopPaiement === $paiement;
                    }
                }
                return false;
            });
        }

        $factures = array_map(function(Paiement $paiement) { return $paiement->getFacture(); }, $items);
        return $factures;
    }

    public function configureColumns(CSVColumns $columns)
    {
        $columns
            ->addColumn('Numero', 'factureId')
            ->addColumn('Statut', 'statut')
            ->addColumn('Montant', 'montant')
            ->addColumn('Payé', 'montantPaye')
            ->addColumn('Rappels', 'rappels.count')
            ->addColumn('Créances', function (Facture $facture) {
                return implode("\n", array_map(function($creance) { return $creance->getTitre(); }, $facture->getCreances()->toArray()));
            })
            ->addColumn('Remarques', 'remarques')
        ;
    }

    /**
     * Returns an alias representing this exporter
     * @return string
     */
    public function getAlias()
    {
        return 'facturation.csv.paiements';
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
        return 'Factures liées';
    }

    /**
     * Returns the form used to configure the export
     * @return string
     */
    public function getConfigFormClass()
    {
        return CSVFacturesPayeesType::class;
    }

    /**
     * Returns the configuration object class
     * @return ExporterConfigInterface|ExporterConfigInterface[]
     */
    public function getBasicConfig()
    {
        return new CSVFactureConfig();
    }

    /**
     * Sets the configuration for this export
     * @param $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * If the rendered file can be previewed, return the used
     * previewer class
     * @return string
     */
    public function getPreviewer()
    {
        return CSVPreviewer::class;
    }
}
