<?php

namespace NetBS\FichierBundle\ListModel;

use NetBS\CoreBundle\ListModel\AbstractDynamicListModel;
use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\CoreBundle\ListModel\Column\RemoveFromDynamicColumn;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Utils\ListModel\MembreListHelper;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\Traits\ConfiguratorTrait;

class DynamicMembreList extends AbstractDynamicListModel
{
    use RouterTrait, ConfiguratorTrait, FichierConfigTrait;

    public function getManagedName()
    {
        return "Membres";
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return $this->getFichierConfig()->getMembreClass();
    }

    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Prénom', null, HelperColumn::class)
            ->addColumn('Date de naissance', 'naissance', DateTimeColumn::class)
            ->addColumn('Fonction', function(BaseMembre $membre) {

                $attr   = $membre->getActiveAttribution();
                if($attr) {
                    return $attr->getFonction()->getNom();
                }

                return "-";

            }, SimpleColumn::class)
            ->addColumn('Unité', function(BaseMembre $membre) {

                $attr   = $membre->getActiveAttribution();
                if($attr)
                    return $attr->getGroupe()->getNom();

                return "-";

            }, SimpleColumn::class)
            ->addColumn("Retirer", null, RemoveFromDynamicColumn::class, [
                'listId'    => $this->getParameter('listId')
            ])
        ;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'netbs.fichier.dynamic.membres';
    }
}