<?php

namespace Ovesco\FacturationBundle\Model;

use Ovesco\FacturationBundle\Entity\FactureModel;

class MergeCreancesToFacture
{
    private $compteToUse;

    private $remarques;

    private $creanceIds;

    private $factureModel;

    /**
     * @return mixed
     */
    public function getCompteToUse()
    {
        return $this->compteToUse;
    }

    /**
     * @param mixed $compteToUse
     */
    public function setCompteToUse($compteToUse)
    {
        $this->compteToUse = $compteToUse;
    }

    /**
     * @return mixed
     */
    public function getRemarques()
    {
        return $this->remarques;
    }

    /**
     * @param mixed $remarques
     */
    public function setRemarques($remarques)
    {
        $this->remarques = $remarques;
    }

    /**
     * @return mixed
     */
    public function getCreanceIds()
    {
        return $this->creanceIds;
    }

    /**
     * @param mixed $creanceIds
     */
    public function setCreanceIds($creanceIds)
    {
        $this->creanceIds = $creanceIds;
    }

    /**
     * @return FactureModel|null
     */
    public function getFactureModel()
    {
        return $this->factureModel;
    }

    /**
     * @param FactureModel|null $factureModel
     */
    public function setFactureModel($factureModel)
    {
        $this->factureModel = $factureModel;
    }
}