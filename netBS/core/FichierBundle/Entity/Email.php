<?php

namespace NetBS\FichierBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Mapping\BaseEmail;

/**
 * Email
 * @ORM\Table(name="netbs_fichier_emails")
 * @ORM\Entity()
 */
class Email extends BaseEmail
{
}

