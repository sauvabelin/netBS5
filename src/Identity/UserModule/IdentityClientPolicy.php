<?php

declare(strict_types=1);

namespace App\Identity\UserModule;

use App\Entity\BSUser;
use App\Identity\Contract\IdentityClientPolicyInterface;
use App\Identity\Contract\IdentityDTO;
use Doctrine\ORM\EntityManagerInterface;

final class IdentityClientPolicy implements IdentityClientPolicyInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function canAccess(IdentityDTO $identity, string $clientId): bool
    {
        $user = $this->em->getRepository(BSUser::class)->findOneBy(['username' => $identity->sub]);
        if (!$user instanceof BSUser) {
            return false;
        }

        return match ($clientId) {
            'nextcloud' => $user->hasNextcloudAccount(),
            'wiki'      => $user->hasWikiAccount(),
            default     => false,
        };
    }

    public function additionalClaimsFor(IdentityDTO $identity, string $clientId): array
    {
        $user = $this->em->getRepository(BSUser::class)->findOneBy(['username' => $identity->sub]);
        if (!$user instanceof BSUser) {
            return [];
        }

        return match ($clientId) {
            'nextcloud' => [
                'nextcloud_admin' => $user->isNextcloudAdmin(),
                'nextcloud_quota' => $user->getNextcloudQuota(),
            ],
            'wiki' => [
                'wiki_admin' => $user->isWikiAdmin(),
            ],
            default => [],
        };
    }
}
