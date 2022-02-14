<?php

namespace Ovesco\FacturationBundle\Util;

use Doctrine\ORM\Mapping as ORM;

trait DateImpressionTrait
{
    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date_impression", type="datetime", nullable=true)
     */
    protected $dateImpression;

    /**
     * @return \DateTime
     */
    public function getDateImpression()
    {
        return $this->dateImpression;
    }

    /**
     * @param \DateTime $dateImpression
     * @return self
     */
    public function setDateImpression($dateImpression)
    {
        $this->dateImpression = $dateImpression;

        return $this;
    }
}
