<?php

namespace Ovesco\FacturationBundle\Searcher;

use NetBS\CoreBundle\Model\BaseBinder;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Form\Type\LatestDateType;

class LatestDateBinder extends BaseBinder
{
    public function bindType()
    {
        return self::POST_FILTER;
    }

    public function getType()
    {
        return LatestDateType::class;
    }

    /**
     * @param Facture $item
     * @param \DateTime $value
     * @param array $options
     * @return bool
     */
    public function postFilter($item, $value, array $options)
    {
        if ($options['property'] === 'impression') {
            if (!$item->getLatestImpression()) return false;
            return $item->getLatestImpression()->format('d.m.Y') === $value->format('d.m.Y');
        }

        else {
            if (!$item->getLatestPaiement()) return false;
            return $item->getLatestPaiement()->getDateEffectivePaiement()->format('d.m.Y') === $value->format('d.m.Y');
        }
    }
}
