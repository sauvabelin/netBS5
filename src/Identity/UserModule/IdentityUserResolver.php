<?php

declare(strict_types=1);

namespace App\Identity\UserModule;

use App\Entity\BSUser;
use NetBS\AuthBundle\Contract\IdentityDTO;
use NetBS\AuthBundle\Contract\IdentityGroupProviderInterface;
use NetBS\AuthBundle\Contract\IdentityUserResolverInterface;
use Doctrine\ORM\EntityManagerInterface;

final class IdentityUserResolver implements IdentityUserResolverInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IdentityGroupProviderInterface $groupProvider,
    ) {
    }

    public function resolveBySub(string $sub): ?IdentityDTO
    {
        // Single DB fetch per resolution. The group provider receives the
        // already-loaded entity (see IdentityGroupProviderInterface::groupsFor)
        // so no second findOneBy is needed, and we build the IdentityDTO once.
        $user = $this->em->getRepository(BSUser::class)->findOneBy(['username' => $sub]);
        if (!$user instanceof BSUser) {
            return null;
        }

        $membre = $user->getMembre();
        $displayName = $membre?->getFullName() ?? $user->getUsername();
        $email = $user->getEmail() ?? $user->getEmailBS();

        return new IdentityDTO(
            sub: $user->getUsername(),
            preferredUsername: $user->getUsername(),
            email: $email,
            emailVerified: $email !== null,
            displayName: $displayName,
            groups: $this->groupProvider->groupsFor($user),
            isDisabled: !$user->getIsActive(),
            updatedAt: new \DateTimeImmutable(),
        );
    }
}
