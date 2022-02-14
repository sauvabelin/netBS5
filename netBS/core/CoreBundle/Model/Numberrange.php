<?php

namespace NetBS\CoreBundle\Model;

class Numberrange
{
    /**
     * @var double
     */
    private $biggerThan = 0;

    /**
     * @var double
     */
    private $lowerThan  = 0;

    /**
     * @return float
     */
    public function getBiggerThan()
    {
        return $this->biggerThan;
    }

    /**
     * @param float $biggerThan
     */
    public function setBiggerThan($biggerThan)
    {
        $this->biggerThan = $biggerThan;
    }

    /**
     * @return float
     */
    public function getLowerThan()
    {
        return $this->lowerThan;
    }

    /**
     * @param float $lowerThan
     */
    public function setLowerThan($lowerThan)
    {
        $this->lowerThan = $lowerThan;
    }
}