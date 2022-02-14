<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Mapping\BaseGroupe;

/**
 * Groupe
 *
 * @ORM\Table(name="netbs_fichier_groupes")
 * @ORM\Entity()
 */
class Groupe extends BaseGroupe
{
}

