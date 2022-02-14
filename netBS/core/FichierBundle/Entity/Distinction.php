<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Mapping\BaseDistinction;


/**
 * Distinction
 * @ORM\Table(name="netbs_fichier_distinctions")
 * @ORM\Entity()
 */
class Distinction extends BaseDistinction
{
}

