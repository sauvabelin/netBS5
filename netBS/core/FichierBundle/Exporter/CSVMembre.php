<?php

namespace NetBS\FichierBundle\Exporter;

use NetBS\CoreBundle\Exporter\CSVColumns;
use NetBS\CoreBundle\Exporter\CSVExporter;
use NetBS\CoreBundle\Exporter\CSVPreviewer;
use NetBS\CoreBundle\Model\ConfigurableExporterInterface;
use NetBS\FichierBundle\Exporter\Config\CSVMembreConfig;
use NetBS\FichierBundle\Form\Export\CSVMembreType;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Service\FichierConfig;

class CSVMembre extends CSVExporter implements ConfigurableExporterInterface
{
    protected $config;

    /**
     * @var CSVMembreConfig
     */
    protected $exportConfig;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function configureColumns(CSVColumns $columns)
    {
        $columns
            ->addColumn('Nom', 'famille.nom')
            ->addColumn('Prénom', 'prenom')
            ->addColumn('Date de naissance', function(BaseMembre $membre) { return $membre->getNaissance()->format('d.m.Y');})
        ;

        if($this->exportConfig->sexe)
            $columns->addColumn('Sexe', 'sexe');

        if($this->exportConfig->unite)
            $columns->addColumn('Unité', function (BaseMembre $membre) {
                if($membre->getActiveAttribution())
                    return $membre->getActiveAttribution()->getGroupe()->getNom();
            });

        if($this->exportConfig->fonction)
            $columns->addColumn('Fonction', function (BaseMembre $membre) {
                if($membre->getActiveAttribution())
                    return $membre->getActiveAttribution()->getFonction()->getNom();
            });

        if($this->exportConfig->adresse) {
            $columns->addColumn('Rue', function (BaseMembre $membre) {
                if($membre->getSendableAdresse())
                    return $membre->getSendableAdresse()->getRue();
            })
            ->addColumn('Npa', function (BaseMembre $membre) {
                if($membre->getSendableAdresse())
                    return $membre->getSendableAdresse()->getNpa();
            })
            ->addColumn('Localité', function (BaseMembre $membre) {
                if($membre->getSendableAdresse())
                    return $membre->getSendableAdresse()->getLocalite();
            });
        }

        if($this->exportConfig->email)
            $columns->addColumn('E-mail', function(BaseMembre $membre) {
                if($membre->getSendableEmail())
                    return $membre->getSendableEmail()->getEmail();
            });

        if($this->exportConfig->telephone)
            $columns->addColumn('Téléphone', function(BaseMembre $membre) {
                if($membre->getSendableTelephone())
                    return $membre->getSendableTelephone()->getTelephone();
            })
        ;
    }

    /**
     * Returns an alias representing this exporter
     * @return string
     */
    public function getAlias()
    {
        return 'csv.membres';
    }

    /**
     * Returns the exported item's class
     * @return string
     */
    public function getExportableClass()
    {
        return $this->config->getMembreClass();
    }

    /**
     * Returns a displayable name of this exporter
     * @return string
     */
    public function getName()
    {
        return 'Liste détaillée';
    }

    /**
     * Returns the form used to configure the export
     * @return string
     */
    public function getConfigFormClass()
    {
        return CSVMembreType::class;
    }

    /**
     * Returns the configuration object class
     * @return string
     */
    public function getBasicConfig()
    {
        return new CSVMembreConfig();
    }

    /**
     * Sets the configuration for this export
     * @param $config
     */
    public function setConfig($config)
    {
        $this->exportConfig = $config;
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
