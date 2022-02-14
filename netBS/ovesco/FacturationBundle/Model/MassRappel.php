<?php

namespace Ovesco\FacturationBundle\Model;

use Ovesco\FacturationBundle\Entity\Rappel;

class MassRappel extends Rappel
{
    /**
     * @var string
     */
    private $selectedIds;

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
}