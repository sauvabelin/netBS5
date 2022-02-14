<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Mapping\BaseMembre;

/**
 * Membre
 * @ORM\Table(name="netbs_fichier_membres")
 * @ORM\Entity()
 */
class Membre extends BaseMembre
{
}