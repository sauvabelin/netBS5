<?php

namespace App\Security;

use App\Entity\BSUser;
use App\Repository\BSUserRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Login forms submit the editable login handle; JWT and remember-me carry the
 * permanent username ({@see BSUser::getUserIdentifier()}). Both resolve here.
 */
class BSUserProvider implements UserProviderInterface
{
    public function __construct(private readonly BSUserRepository $users)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->users->findOneByHandle($identifier);
        if ($user === null) {
            $e = new UserNotFoundException();
            $e->setUserIdentifier($identifier);
            throw $e;
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof BSUser) {
            throw new UnsupportedUserException(sprintf('Unsupported user class "%s".', $user::class));
        }

        // By id, like Doctrine's entity provider: survives any rename.
        $fresh = $this->users->find($user->getId());
        if ($fresh === null) {
            $e = new UserNotFoundException();
            $e->setUserIdentifier($user->getUserIdentifier());
            throw $e;
        }

        return $fresh;
    }

    public function supportsClass(string $class): bool
    {
        return $class === BSUser::class || is_subclass_of($class, BSUser::class);
    }
}
