<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Mapping\BaseAttribution;

/**
 * Attribution
 * @ORM\Table(name="netbs_fichier_attributions")
 * @ORM\Entity()
 */
class Attribution extends BaseAttribution
{
}

