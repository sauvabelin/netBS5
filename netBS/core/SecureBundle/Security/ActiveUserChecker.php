<?php

namespace NetBS\SecureBundle\Security;

use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuses deactivated accounts on every way in (login form, remember-me, API token).
 * Sessions opened before the deactivation are ended by BSUserProvider::refreshUser().
 */
class ActiveUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof BaseUser && !$user->getIsActive()) {
            throw new DisabledException();
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
    }
}
