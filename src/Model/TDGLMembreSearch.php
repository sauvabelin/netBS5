<?php

namespace App\Model;

use NetBS\FichierBundle\Model\Search\SearchBaseMembreInformation;

class TDGLMembreSearch extends SearchBaseMembreInformation
{
    protected $totem;

    protected $anciens;

    /**
     * @return mixed
     */
    public function getTotem()
    {
        return $this->totem;
    }

    /**
     * @param mixed $totem
     */
    public function setTotem($totem)
    {
        $this->totem = $totem;
    }

    /**
     * @return mixed
     */
    public function getAnciens()
    {
        return $this->anciens;
    }

    /**
     * @param mixed $anciens
     */
    public function setAnciens($anciens)
    {
        $this->anciens = $anciens;
    }
}
