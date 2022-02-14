<?php

namespace NetBS\SecureBundle\Model;

use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

abstract class BaseUserProvider implements UserProviderInterface
{
    abstract public function createUser(BaseUser $user);

    abstract public function updateUser(BaseUser $user);

    abstract public function deleteUser(BaseUser $user);

    abstract public function refresh(BaseUser $user);

    public function refreshUser(UserInterface $user) {

        $this->refresh($user);
    }
}