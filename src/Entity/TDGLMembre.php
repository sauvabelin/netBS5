<?php

namespace App\Entity;

use NetBS\FichierBundle\Mapping\BaseMembre;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class TDGLMembre
 * @package TDGLBundle\Entity
 * @ORM\Entity
 * @ORM\Table(name="tdgl_membres")
 */
class TDGLMembre extends BaseMembre
{
    /**
     * @var string
     * @ORM\Column(name="totem", type="string", length=255, nullable=true)
     */
    protected $totem;

    /**
     * @return string
     */
    public function getTotem()
    {
        return $this->totem;
    }

    /**
     * @param string $totem
     * @return TDGLMembre
     */
    public function setTotem($totem)
    {
        $this->totem = $totem;

        return $this;
    }
}
