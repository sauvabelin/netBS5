<?php

namespace NetBS\FichierBundle\Utils\ListModel;

use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\ListColumnsConfiguration;

trait MembreListHelper
{
    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Prénom', null, HelperColumn::class)
            ->addColumn('Date de naissance', 'naissance', DateTimeColumn::class)
            ->addColumn('actuellement', function(BaseMembre $membre) {

                $attr   = $membre->getActiveAttribution();
                if($attr) {
                    return $attr->getFonction()->getNom() . " - " . $attr->getGroupe()->getNom();
                }

                return "-";

            }, SimpleColumn::class)
        ;
    }

    public function getManagedItemsClass()
    {
        return $this->getFichierConfig()->getMembreClass();
    }
}