<?php

namespace NetBS\FichierBundle\Bridge;

use NetBS\CoreBundle\Model\BridgeInterface;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Service\FichierConfig;

class GroupeToAttribution implements BridgeInterface
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    /**
     * The given object class
     * @return string
     */
    public function getFromClass()
    {
        return $this->config->getGroupeClass();
    }

    /**
     * The outputed item class
     * @return string
     */
    public function getToClass()
    {
        return $this->config->getAttributionClass();
    }

    /**
     * Returns an estimation of the cost of the transformation (if multiple requests are needed...). Must be >= 0
     * @return int
     */
    public function getCost()
    {
        return 2;
    }

    /**
     * Converts $from an array of fromClass to an array of class toClass
     * @param BaseGroupe[] $from
     * @return BaseAttribution[]
     */
    public function transform($from)
    {
        $result     = [];
        $resultIds  = [];

        foreach($from as $groupe) {

            foreach($groupe->getActivesAttributions() as $attribution) {

                if(!in_array($attribution->getId(), $resultIds)) {

                    $result[]       = $attribution;
                    $resultIds[]    = $attribution->getId();
                }
            }
        }

        return $result;
    }
}