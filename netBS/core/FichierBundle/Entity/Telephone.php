<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Mapping\BaseTelephone;


/**
 * Telephone
 *
 * @ORM\Table(name="netbs_fichier_telephones")
 * @ORM\Entity()
 */
class Telephone extends BaseTelephone
{
}

