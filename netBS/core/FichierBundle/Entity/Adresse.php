<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Mapping\BaseAdresse;

/**
 * Adresse
 * @ORM\Table(name="netbs_fichier_adresses")
 * @ORM\Entity()
 */
class Adresse extends BaseAdresse
{
}

