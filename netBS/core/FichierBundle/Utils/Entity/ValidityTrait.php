<?php

namespace NetBS\FichierBundle\Utils\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

trait ValidityTrait
{
    /**
     * @ORM\Column(type="string", length=255, nullable=false)
     * @Assert\NotBlank()
     */
    protected $validity;

    /**
     * Set validity
     *
     * @param integer $validity
     * @return self
     */
    public function setValidity($validity)
    {
        $this->validity = $validity;
        return $this;
    }

    /**
     * Get validity
     *
     * @return integer $validity
     */
    public function getValidity()
    {
        return $this->validity;
    }
}