<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Mapping\BaseFonction;

/**
 * Fonction
 *
 * @ORM\Table(name="netbs_fichier_fonctions")
 * @ORM\Entity()
 */
class Fonction extends BaseFonction
{
}

