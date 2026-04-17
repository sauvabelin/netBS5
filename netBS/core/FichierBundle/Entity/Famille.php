<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use NetBS\FichierBundle\Mapping\BaseFamille;

/**
 * Famille
 */
#[ORM\Table(name: 'netbs_fichier_familles')]
#[ORM\Entity]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Famille extends BaseFamille
{
}

