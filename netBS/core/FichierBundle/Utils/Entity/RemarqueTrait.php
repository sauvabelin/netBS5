<?php

namespace NetBS\FichierBundle\Utils\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

trait RemarqueTrait
{
    /**
     * @Gedmo\Versioned
     * @ORM\Column(type="text", nullable=true)
     */
    protected $remarques;

    /**
     * Set remarques
     *
     * @param string $remarques
     * @return self
     */
    public function setRemarques($remarques)
    {
        $this->remarques = $remarques;
        return $this;
    }

    /**
     * Get remarques
     *
     * @return string $remarques
     */
    public function getRemarques()
    {
        return $this->remarques;
    }
}