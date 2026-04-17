<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use NetBS\FichierBundle\Mapping\BaseObtentionDistinction;

/**
 * ObtentionDistinction
 */
#[ORM\Table(name: 'netbs_fichier_obtentions_distinction')]
#[ORM\Entity]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class ObtentionDistinction extends BaseObtentionDistinction
{
}

