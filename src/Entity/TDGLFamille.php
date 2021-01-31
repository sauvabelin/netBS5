<?php

namespace App\Entity;

use NetBS\FichierBundle\Mapping\BaseFamille;
use Doctrine\ORM\Mapping as ORM;

/**
 * Class TDGLFamille
 * @package TDGLBundle\Entity
 * @ORM\Entity
 * @ORM\Table(name="tdgl_familles")
 */
class TDGLFamille extends BaseFamille
{
    /**
     * @var string
     * @ORM\Column(name="professions_parents", type="text", nullable=true)
     */
    protected $professionsParents;

    /**
     * @return string
     */
    public function getProfessionsParents()
    {
        return $this->professionsParents;
    }

    /**
     * @param string $professionsParents
     * @return TDGLFamille
     */
    public function setProfessionsParents($professionsParents)
    {
        $this->professionsParents = $professionsParents;

        return $this;
    }
}
