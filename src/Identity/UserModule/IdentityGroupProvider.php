<?php

declare(strict_types=1);

namespace App\Identity\UserModule;

use App\Entity\BSUser;
use NetBS\AuthBundle\Contract\IdentityGroupProviderInterface;

final class IdentityGroupProvider implements IdentityGroupProviderInterface
{
    /**
     * @param object $user The already-loaded BSUser. Typed `object` to satisfy
     *                     the contract; we narrow at runtime. The resolver
     *                     passes the entity it just fetched, so this method
     *                     does no DB work of its own.
     */
    public function groupsFor(object $user): array
    {
        if (!$user instanceof BSUser) {
            return [];
        }

        $membre = $user->getMembre();
        if ($membre === null) {
            return [];
        }

        $groupNames = [];
        foreach ($membre->getActivesAttributions() as $attribution) {
            $groupNames[$attribution->getGroupe()->getNom()] = true;
        }
        return array_keys($groupNames);
    }
}
