<?php

namespace NetBS\SecureBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\SecureBundle\Mapping\BaseRole;

/**
 * Role
 *
 * @ORM\Table(name="netbs_secure_roles")
 * @ORM\Entity()
 */
class Role extends BaseRole
{
}

