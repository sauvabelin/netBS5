<?php

namespace Ovesco\FacturationBundle\ListModel;

use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use Ovesco\FacturationBundle\Util\FactureListTrait;

class FacturesAttentePaiementList extends AbstractFacturesImpressionList
{
    use EntityManagerTrait, FactureListTrait;

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'ovesco.facturation.factures_attente_paiement';
    }

    protected function hasBeenPrinted(): bool
    {
        return true;
    }
}