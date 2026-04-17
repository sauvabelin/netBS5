<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use NetBS\FichierBundle\Mapping\BaseMembre;

/**
 * Membre
 */
#[ORM\Table(name: 'netbs_fichier_membres')]
#[ORM\Entity]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class Membre extends BaseMembre
{
}