<?php

namespace NetBS\FichierBundle\Model\Search;

use NetBS\CoreBundle\Model\Daterange;
use NetBS\FichierBundle\Mapping\BaseDistinction;

class SearchObtentionDistinction
{
    /**
     * @var BaseDistinction
     */
    protected $distinction;

    /**
     * @var Daterange
     */
    protected $date;

    /**
     * @return BaseDistinction
     */
    public function getDistinction()
    {
        return $this->distinction;
    }

    /**
     * @param BaseDistinction $distinction
     */
    public function setDistinction($distinction)
    {
        $this->distinction = $distinction;
    }

    /**
     * @return Daterange
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param Daterange $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }
}