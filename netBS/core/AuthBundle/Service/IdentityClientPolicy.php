<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Service;

use App\Entity\BSUser;
use NetBS\AuthBundle\Contract\IdentityClientPolicyInterface;
use NetBS\AuthBundle\Contract\IdentityDTO;
use Doctrine\ORM\EntityManagerInterface;

final class IdentityClientPolicy implements IdentityClientPolicyInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function canAccess(IdentityDTO $identity, string $clientId): bool
    {
        // Default-allow: any registered user can authenticate to any client
        // that's been provisioned in Hydra. Per-client access rules will be
        // added back via Hydra client metadata (see TODO in OidcClientDto).
        $user = $this->em->getRepository(BSUser::class)->findOneBy(['username' => $identity->sub]);
        return $user instanceof BSUser && !$identity->isDisabled;
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
