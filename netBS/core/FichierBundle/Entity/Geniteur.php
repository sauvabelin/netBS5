<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use NetBS\FichierBundle\Mapping\BaseGeniteur;

/**
 * Geniteur
 */
#[ORM\Table(name: 'netbs_fichier_geniteurs')]
#[ORM\Entity]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Geniteur extends BaseGeniteur
{
}

