<?php

namespace NetBS\FichierBundle\Bridge;

use Doctrine\ORM\EntityManager;
use NetBS\CoreBundle\Model\BridgeInterface;
use NetBS\FichierBundle\Model\AdressableInterface;
use NetBS\FichierBundle\Service\FichierConfig;

class FamilleToAdressable implements BridgeInterface
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
        return $this->config->getFamilleClass();
    }

    /**
     * The outputed item class
     * @return string
     */
    public function getToClass()
    {
        return AdressableInterface::class;
    }

    /**
     * Returns an estimation of the cost of the transformation (if multiple requests are needed...). Must be >= 0
     * @return int
     */
    public function getCost()
    {
        return 0;
    }

    /**
     * Converts $from an array of fromClass to an array of class toClass
     * @param object[] $from
     * @return object[]
     */
    public function transform($from)
    {
        return $from;
    }
}
