<?php

namespace Ovesco\FacturationBundle\Model;

use Ovesco\FacturationBundle\Entity\FactureModel;

class MassAssignModel
{
    private $selectedIds;

    private $factureModel;

    public function getSelectedIds()
    {
        return $this->selectedIds;
    }

    public function setSelectedIds($selectedIds)
    {
        $this->selectedIds = $selectedIds;
    }

    public function getFactureModel(): ?FactureModel
    {
        return $this->factureModel;
    }

    public function setFactureModel(?FactureModel $factureModel)
    {
        $this->factureModel = $factureModel;
    }
}
