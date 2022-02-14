<?php

namespace NetBS\FichierBundle\Utils\Entity;

use Doctrine\ORM\Mapping as ORM;

trait ExpediableTrait
{
    /**
     * @ORM\Column(type="boolean")
     */
    protected $expediable = true;

    /**
     * Set expediable
     *
     * @param boolean $expediable
     * @return self
     */
    public function setExpediable($expediable)
    {
        $this->expediable = $expediable;
        return $this;
    }

    /**
     * Get expediable
     *
     * @return boolean $expediable
     */
    public function getExpediable()
    {
        return $this->expediable;
    }
}