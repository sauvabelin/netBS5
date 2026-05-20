<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Service;

use NetBS\SecureBundle\Audit\AccessGrant;
use NetBS\SecureBundle\Audit\Provenance;
use NetBS\SecureBundle\Audit\UserAccessReport;
use NetBS\SecureBundle\Mapping\BaseRole;
use NetBS\SecureBundle\Mapping\BaseUser;

final class AccessAuditService
{
    /** Hard-coded curated list — same set already special-cased in DebugUserRolesCommand. */
    public const SENSITIVE_ROLES = [
        'ROLE_ADMIN',
        'ROLE_COMMANDANT',
        'ROLE_SG',
        'ROLE_TRESORIER',
        'ROLE_APMBS',
        'ROLE_READ_EVERYWHERE',
    ];

    public function auditUser(BaseUser $user): UserAccessReport
    {
        $grants = [
            ...$this->directRoleGrants($user),
            ...$this->fonctionRoleGrants($user),
            ...$this->autorisationGrants($user),
        ];

        return new UserAccessReport($user, $grants);
    }

    /** @return AccessGrant[] */
    private function directRoleGrants(BaseUser $user): array
    {
        $direct = $user->getDirectRoles();
        if (empty($direct)) {
            return [];
        }

        return [new AccessGrant(
            provenance: Provenance::DIRECT_ROLE,
            sourceFonction: null,
            roles: $this->expand($direct),
            scope: null,
        )];
    }

    /** @return AccessGrant[] */
    private function fonctionRoleGrants(BaseUser $user): array
    {
        $membre = $user->getMembre();
        if (!$membre) {
            return [];
        }

        $grants = [];
        foreach ($membre->getActivesAttributions() as $attribution) {
            $fonction = $attribution->getFonction();
            $grants[] = new AccessGrant(
                provenance: Provenance::FONCTION_ROLE,
                sourceFonction: $fonction,
                roles: $this->expand($fonction->getRoles()),
                scope: $attribution->getGroupe(),
                sourceId: $attribution->getId(),
            );
        }
        return $grants;
    }

    /** @return AccessGrant[] */
    private function autorisationGrants(BaseUser $user): array
    {
        $grants = [];
        foreach ($user->getAutorisations() as $autorisation) {
            $grants[] = new AccessGrant(
                provenance: Provenance::AUTORISATION,
                sourceFonction: null,
                roles: $this->expand($autorisation->getRoles()),
                scope: $autorisation->getGroupe(),
                sourceId: $autorisation->getId(),
            );
        }
        return $grants;
    }

    /**
     * @param iterable<BaseRole> $roles
     * @return BaseRole[]
     */
    private function expand(iterable $roles): array
    {
        $out = [];
        foreach ($roles as $r) {
            foreach ($r->getChildrenRecursive() as $child) {
                $out[$child->getRole()] = $child;
            }
        }
        return array_values($out);
    }
}
