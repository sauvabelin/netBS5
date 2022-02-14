<?php

namespace NetBS\SecureBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\SecureBundle\Mapping\BaseAutorisation;

/**
 * @ORM\Table(name="netbs_secure_autorisations")
 * @ORM\Entity()
 */
class Autorisation extends BaseAutorisation
{
}

