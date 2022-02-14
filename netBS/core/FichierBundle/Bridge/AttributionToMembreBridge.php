<?php

namespace NetBS\FichierBundle\Bridge;

use NetBS\CoreBundle\Model\BridgeInterface;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Service\FichierConfig;

class AttributionToMembreBridge implements BridgeInterface
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    public function getFromClass()
    {
        return $this->config->getAttributionClass();
    }

    public function getToClass()
    {
        return $this->config->getMembreClass();
    }

    public function getCost()
    {
        return 1;
    }

    /**
     * @param BaseAttribution[] $from
     * @return BaseMembre[]
     */
    public function transform($from)
    {
        $result = [];
        foreach($from as $attribution)
            $result[] = $attribution->getMembre();

        return $result;
    }
}