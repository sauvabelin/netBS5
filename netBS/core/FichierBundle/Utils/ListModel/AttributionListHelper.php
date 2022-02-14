<?php

namespace NetBS\FichierBundle\Utils\ListModel;

use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\ListColumnsConfiguration;

trait AttributionListHelper
{
    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Prénom', 'membre', HelperColumn::class)
            ->addColumn('Date de naissance', 'membre.naissance', DateTimeColumn::class)
            ->addColumn('Unité', 'groupe', HelperColumn::class)
            ->addColumn('Fonction', 'fonction', SimpleColumn::class)
        ;
    }

    public function getManagedItemsClass()
    {
        return $this->getFichierConfig()->getAttributionClass();
    }
}