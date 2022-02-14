<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Mapping\BaseFamille;

/**
 * Famille
 *
 * @ORM\Table(name="netbs_fichier_familles")
 * @ORM\Entity()
 */
class Famille extends BaseFamille
{
}

