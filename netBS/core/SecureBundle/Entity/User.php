<?php

namespace NetBS\SecureBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\SecureBundle\Mapping\BaseUser;

/**
 * User
 * @ORM\Table(name="netbs_secure_users")
 * @ORM\Entity
 */
class User extends BaseUser
{
}

