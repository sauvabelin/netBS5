<?php

namespace Ovesco\FacturationBundle\Model;

use Ovesco\FacturationBundle\Entity\Creance;

class MassCreances extends Creance
{
    /**
     * @var string
     */
    private $selectedIds;

    /**
     * @var string
     */
    private $itemsClass;

    /**
     * @var int
     */
    public $amount;

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
        $this->amount = count(unserialize($selectedIds));
    }

    /**
     * @return string
     */
    public function getItemsClass()
    {
        return $this->itemsClass;
    }

    /**
     * @param string $itemsClass
     */
    public function setItemsClass($itemsClass)
    {
        $this->itemsClass = $itemsClass;
    }

    public function toCreance() {

        $creance    = new Creance();
        $creance
            ->setMontant($this->montant)
            ->setRabais($this->rabais)
            ->setRabaisIfInFamille($this->rabaisIfInFamille)
            ->setTitre($this->titre)
            ->setRemarques($this->remarques);

        return $creance;
    }
}
