<?php

declare(strict_types=1);

namespace App\Identity\UserModule;

use App\Entity\BSUser;
use NetBS\AuthBundle\Contract\IdentityDTO;
use NetBS\AuthBundle\Contract\IdentityUserResolverInterface;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Contract\Subject;

final class IdentityUserResolver implements IdentityUserResolverInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function resolveBySub(string $sub): ?IdentityDTO
    {
        $user = $this->em->getRepository(BSUser::class)->findOneBy(['username' => $sub]);
        if (!$user instanceof BSUser) {
            return null;
        }

        return new IdentityDTO(
            sub: Subject::fromUsername($user->getUsername()),
            preferredUsername: Subject::fromUsername($user->getUsername()),
            email: BSUserIdentity::email($user),
            displayName: BSUserIdentity::displayName($user),
            isDisabled: !$user->getIsActive(),
        );
    }
}
