<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Gedmo\Mapping\Annotation as Gedmo;
use NetBS\FichierBundle\Mapping\BaseContactInformation;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class ContactInformation
 * @package FichierBundle\Entity
 */
#[ORM\Entity]
#[ORM\Table(name: 'netbs_fichier_contact_informations')]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt', timeAware: false, hardDelete: false)]
class ContactInformation extends BaseContactInformation
{

}