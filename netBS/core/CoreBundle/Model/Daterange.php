<?php

namespace NetBS\CoreBundle\Model;

class Daterange
{
    /**
     * @var \DateTime
     */
    private $biggerThan;

    /**
     * @var \DateTime
     */
    private $lowerThan;

    public function __construct()
    {
        $this->biggerThan   = new \DateTime();
        $this->lowerThan    = new \DateTime();
    }

    /**
     * @return \DateTime
     */
    public function getBiggerThan()
    {
        return $this->biggerThan;
    }

    /**
     * @param \DateTime $biggerThan
     */
    public function setBiggerThan($biggerThan)
    {
        $this->biggerThan = $biggerThan;
    }

    /**
     * @return \DateTime
     */
    public function getLowerThan()
    {
        return $this->lowerThan;
    }

    /**
     * @param \DateTime $lowerThan
     */
    public function setLowerThan($lowerThan)
    {
        $this->lowerThan = $lowerThan;
    }
}