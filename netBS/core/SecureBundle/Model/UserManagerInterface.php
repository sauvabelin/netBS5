<?php

namespace NetBS\SecureBundle\Model;

use NetBS\SecureBundle\Mapping\BaseUser;

interface UserManagerInterface
{
    public function find($id);

    public function createUser(BaseUser $user);

    public function updateUser(BaseUser $user);

    public function deleteUser(BaseUser $user);
}