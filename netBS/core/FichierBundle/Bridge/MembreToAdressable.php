<?php

namespace NetBS\FichierBundle\Bridge;

use NetBS\CoreBundle\Model\BridgeInterface;
use NetBS\FichierBundle\Model\AdressableInterface;
use NetBS\FichierBundle\Service\FichierConfig;

class MembreToAdressable implements BridgeInterface
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
        return $this->config->getMembreClass();
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
