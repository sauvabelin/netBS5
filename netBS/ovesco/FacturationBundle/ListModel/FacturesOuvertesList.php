<?php

namespace Ovesco\FacturationBundle\ListModel;

use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\ListBundle\Model\BaseListModel;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Util\FactureListTrait;

class FacturesOuvertesList extends BaseListModel
{
    use EntityManagerTrait, FactureListTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->getRepository('OvescoFacturationBundle:Facture')->findBy(['statut' => Facture::OUVERTE]);
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'ovesco.facturation.factures_ouvertes';
    }
}