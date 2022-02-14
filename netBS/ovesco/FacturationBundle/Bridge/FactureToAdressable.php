<?php

namespace Ovesco\FacturationBundle\Bridge;

use NetBS\CoreBundle\Model\BridgeInterface;
use NetBS\FichierBundle\Model\AdressableInterface;
use Ovesco\FacturationBundle\Entity\Facture;

class FactureToAdressable implements BridgeInterface
{
    /**
     * The given object class
     * @return string
     */
    public function getFromClass()
    {
        return Facture::class;
    }

    /**
     * The outputed item class
     * @return string
     */
    public function getToClass()
    {
        return AdressableInterface::class;
    }

    public function getCost()
    {
        return 1;
    }

    /**
     * Converts $from of class fromClass to an object of class toClass
     * @param Facture[] $from
     * @return object[]
     */
    public function transform($from)
    {
        $result = [];
        foreach($from as $item)
            $result[] = $item->getDebiteur();

        return $result;
    }
}