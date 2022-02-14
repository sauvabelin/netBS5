<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use NetBS\FichierBundle\Mapping\BaseContactInformation;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class ContactInformation
 * @package FichierBundle\Entity
 * @ORM\Entity()
 * @ORM\Table(name="netbs_fichier_contact_informations")
 */
class ContactInformation extends BaseContactInformation
{

}