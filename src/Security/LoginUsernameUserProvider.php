<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Resolves the name typed on a login form (netBS form, gettoken API). Only the
 * editable login username signs in; the permanent username never does.
 */
class LoginUsernameUserProvider extends BSUserProvider
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        return $this->found($this->users->findOneByLoginUsername($identifier), $identifier);
    }
}
