<?php

declare(strict_types=1);

namespace App\Identity\UserModule;

use App\Entity\BSUser;
use NetBS\AuthBundle\Contract\IdentityDTO;
use NetBS\AuthBundle\Contract\IdentityUserResolverInterface;
use Doctrine\ORM\EntityManagerInterface;

final class IdentityUserResolver implements IdentityUserResolverInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly IdentityGroupProvider $groupProvider,
    ) {
    }

    public function resolveBySub(string $sub): ?IdentityDTO
    {
        $user = $this->em->getRepository(BSUser::class)->findOneBy(['username' => $sub]);
        if (!$user instanceof BSUser) {
            return null;
        }

        $membre = $user->getMembre();
        $displayName = $membre?->getFullName() ?? $user->getUsername();
        $email = $user->getEmail() ?? $user->getEmailBS();

        $dto = new IdentityDTO(
            sub: $user->getUsername(),
            preferredUsername: $user->getUsername(),
            email: $email,
            emailVerified: $email !== null,
            displayName: $displayName,
            groups: [],
            isDisabled: !$user->getIsActive(),
            updatedAt: new \DateTimeImmutable(),
        );

        return new IdentityDTO(
            sub: $dto->sub,
            preferredUsername: $dto->preferredUsername,
            email: $dto->email,
            emailVerified: $dto->emailVerified,
            displayName: $dto->displayName,
            groups: $this->groupProvider->groupsFor($dto),
            isDisabled: $dto->isDisabled,
            updatedAt: $dto->updatedAt,
        );
    }
}
