<?php

namespace App\Model;

use NetBS\FichierBundle\Model\Search\SearchBaseMembreInformation;

class SearchMembre extends SearchBaseMembreInformation
{
    /**
     * @var boolean
     */
    private $noAdabs;

    /**
     * @var boolean
     */
    private $noApmbs;

    /**
     * @return bool
     */
    public function isNoAdabs()
    {
        return $this->noAdabs;
    }

    /**
     * @param bool $noAdabs
     */
    public function setNoAdabs($noAdabs)
    {
        $this->noAdabs = $noAdabs;
    }

    /**
     * @return bool
     */
    public function isNoApmbs()
    {
        return $this->noApmbs;
    }

    /**
     * @param bool $noApmbs
     */
    public function setNoApmbs($noApmbs)
    {
        $this->noApmbs = $noApmbs;
    }
}
