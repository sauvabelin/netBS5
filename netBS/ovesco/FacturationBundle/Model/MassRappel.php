<?php

namespace Ovesco\FacturationBundle\Model;

use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Entity\Rappel;

class MassRappel extends Rappel
{
    /**
     * @var string
     */
    private $selectedIds;

    /**
     * @var FactureModel|null
     */
    private $factureModel;

    /**
     * @return string
     */
    public function getSelectedIds()
    {
        return $this->selectedIds;
    }

    /**
     * @param string $selectedIds
     */
    public function setSelectedIds($selectedIds)
    {
        $this->selectedIds = $selectedIds;
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