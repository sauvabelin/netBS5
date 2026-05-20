<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Service;

use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\SecureBundle\Audit\AccessGrant;
use NetBS\SecureBundle\Audit\Provenance;
use NetBS\SecureBundle\Audit\ScopeAccessEntry;
use NetBS\SecureBundle\Audit\ScopeAccessReport;
use NetBS\SecureBundle\Audit\SensitiveRoleCell;
use NetBS\SecureBundle\Audit\UserAccessReport;
use NetBS\SecureBundle\Mapping\BaseRole;
use NetBS\SecureBundle\Mapping\BaseUser;

final class AccessAuditService
{
    /** @var \Closure(BaseGroupe[]): iterable|null  Test seam: returns Autorisation[] for a set of Groupes. */
    private ?\Closure $autorisationFinder = null;

    /** @var \Closure(BaseGroupe[]): iterable|null  Test seam: returns active Attribution[] for a set of Groupes. */
    private ?\Closure $attributionFinder = null;

    public function __construct(
        private readonly \Doctrine\ORM\EntityManagerInterface $em,
        private readonly \NetBS\SecureBundle\Service\SecureConfig $secureConfig,
        private readonly \NetBS\FichierBundle\Service\FichierConfig $fichierConfig,
    ) {}

    /** Hard-coded curated list — same set already special-cased in DebugUserRolesCommand. */
    public const SENSITIVE_ROLES = [
        'ROLE_ADMIN',
        'ROLE_COMMANDANT',
        'ROLE_SG',
        'ROLE_QM',
        'ROLE_TRESORIER',
        'ROLE_APMBS',
        'ROLE_MAILING',
        'ROLE_READ_EVERYWHERE',
        'ROLE_CREATE_EVERYWHERE',
        'ROLE_UPDATE_EVERYWHERE',
        'ROLE_DELETE_EVERYWHERE',
    ];

    /**
     * Roles that bypass group-scope checks in voters:
     * - ROLE_ADMIN: short-circuits every NetBSVoter via specialRule().
     * - ROLE_SG: short-circuits every FichierVoter via specialRule().
     * - ROLE_*_EVERYWHERE: short-circuits the matching CRUD operation in
     *   NetBSVoter::voteOnAttribute regardless of scope.
     *
     * A user holding any of these (directly, via a fonction, via an
     * autorisation, or via an ancestor role) can access every group, so the
     * group-scope audit must surface them even when no autorisation targets
     * the audited group.
     */
    public const GLOBAL_OVERRIDE_ROLES = [
        'ROLE_ADMIN',
        'ROLE_SG',
        'ROLE_READ_EVERYWHERE',
        'ROLE_CREATE_EVERYWHERE',
        'ROLE_UPDATE_EVERYWHERE',
        'ROLE_DELETE_EVERYWHERE',
    ];

    /**
     * Builds a "who has the keys" matrix: rows are users holding at least one
     * sensitive role, columns are the sensitive roles. Each cell carries the
     * AccessGrants and whether at least one of them is explicit (holds the
     * audited role itself, not just an ancestor role that contains it).
     *
     * @return array{
     *     roles: string[],
     *     rows: list<array{user: BaseUser, cells: array<string, SensitiveRoleCell>}>,
     *     counts: array{explicit: array<string, int>, inherited: array<string, int>},
     * }
     */
    public function buildSensitiveRoleMatrix(): array
    {
        $repo = $this->em->getRepository($this->secureConfig->getRoleClass());
        /** @var BaseRole[] $roles */
        $roles = $repo->createQueryBuilder('r')
            ->where('r.role IN (:names)')
            ->setParameter('names', self::SENSITIVE_ROLES)
            ->getQuery()->getResult();

        $byUid     = [];
        $explicit  = array_fill_keys(self::SENSITIVE_ROLES, 0);
        $inherited = array_fill_keys(self::SENSITIVE_ROLES, 0);

        foreach ($roles as $role) {
            $name = $role->getRole();
            foreach ($this->auditRoleScope($role)->entries as $entry) {
                $cellExplicit = false;
                foreach ($entry->grants as $g) {
                    foreach ($g->roles as $r) {
                        if ($r->getRole() === $name) {
                            $cellExplicit = true;
                            break 2;
                        }
                    }
                }

                $uid = $entry->user->getId();
                $byUid[$uid] ??= ['user' => $entry->user, 'cells' => []];
                $byUid[$uid]['cells'][$name] = new SensitiveRoleCell($entry->grants, $cellExplicit);
                $cellExplicit ? $explicit[$name]++ : $inherited[$name]++;
            }
        }

        usort($byUid, fn($a, $b) => strcmp(
            (string) $a['user']->getUsername(),
            (string) $b['user']->getUsername(),
        ));

        return [
            'roles'  => self::SENSITIVE_ROLES,
            'rows'   => array_values($byUid),
            'counts' => ['explicit' => $explicit, 'inherited' => $inherited],
        ];
    }

    public function auditUser(BaseUser $user): UserAccessReport
    {
        $grants = [
            ...$this->directRoleGrants($user),
            ...$this->fonctionRoleGrants($user),
            ...$this->autorisationGrants($user),
        ];

        $sensitiveNames = [];
        $scopeIds = [];
        foreach ($grants as $g) {
            foreach ($g->roles as $r) {
                if (in_array($r->getRole(), self::SENSITIVE_ROLES, true)) {
                    $sensitiveNames[$r->getRole()] = true;
                }
            }
            if ($g->scope !== null) {
                $scopeIds[$g->scope->getId()] = true;
            }
        }

        return new UserAccessReport($user, $grants, count($sensitiveNames), count($scopeIds));
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
            explicitRoleNames: $this->roleNames($direct),
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
                explicitRoleNames: $this->roleNames($fonction->getRoles()),
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
                explicitRoleNames: $this->roleNames($autorisation->getRoles()),
                scope: $autorisation->getGroupe(),
                sourceId: $autorisation->getId(),
            );
        }
        return $grants;
    }

    /**
     * @param  iterable<BaseRole> $roles
     * @return string[]
     */
    private function roleNames(iterable $roles): array
    {
        $names = [];
        foreach ($roles as $r) {
            $names[] = $r->getRole();
        }
        return $names;
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

    public function auditScope(BaseGroupe|BaseRole $scope): ScopeAccessReport
    {
        if ($scope instanceof BaseGroupe) {
            return $this->auditGroupeScope($scope);
        }
        return $this->auditRoleScope($scope);
    }

    private function auditRoleScope(BaseRole $role): ScopeAccessReport
    {
        // Walk UP the role tree: holding the role itself or any ancestor effectively
        // grants the audited role (per BaseUser::getAllRoles, which expands children).
        $ancestorNames = [];
        for ($r = $role; $r !== null; $r = $r->getParent()) {
            $ancestorNames[] = $r->getRole();
        }

        return new ScopeAccessReport($role, $this->toEntries($this->collectAccessForRoleNames($ancestorNames)));
    }

    /**
     * Returns the byUser map (keyed by spl_object_id) of all users effectively
     * holding any role in $names — including the path (direct / fonction /
     * autorisation) by which they hold it. The role lists in the returned
     * grants are filtered to the input names so the caller sees exactly which
     * of the requested roles each grant contributes.
     *
     * @param  string[] $names Role names to match against direct roles, fonction
     *                         roles, and autorisation roles. Typically a role and
     *                         its ancestors (since an ancestor's children expand
     *                         down to grant the role per BaseUser::getAllRoles).
     * @return array<int, array{user: BaseUser, grants: list<AccessGrant>}>
     */
    private function collectAccessForRoleNames(array $names): array
    {
        $byUser = [];
        if (empty($names)) {
            return $byUser;
        }

        // 1. Direct holders.
        $userRepo = $this->em->getRepository($this->secureConfig->getUserClass());
        $directHolders = $userRepo->createQueryBuilder('u')
            ->innerJoin('u.roles', 'r')
            ->where('r.role IN (:names)')
            ->setParameter('names', $names)
            ->distinct()
            ->getQuery()->getResult();

        foreach ($directHolders as $u) {
            $held = $this->filterToAncestors($u->getDirectRoles(), $names);
            $byUser[spl_object_id($u)] = [
                'user'   => $u,
                'grants' => [new AccessGrant(
                    provenance: Provenance::DIRECT_ROLE,
                    sourceFonction: null,
                    roles: $held,
                    explicitRoleNames: $this->roleNames($held),
                    scope: null,
                )],
            ];
        }

        // 2. Fonction holders (via active attributions). addSelect eager-loads
        // the fonction and its full roles collection so the loop below doesn't
        // trigger lazy loads on getFonction()->getRoles().
        $now = new \DateTime();
        $attrRepo = $this->em->getRepository($this->fichierConfig->getAttributionClass());
        $attributions = $attrRepo->createQueryBuilder('a')
            ->addSelect('f', 'fr_all')
            ->innerJoin('a.fonction', 'f')
            ->innerJoin('f.roles', 'fr')
            ->leftJoin('f.roles', 'fr_all')
            ->where('fr.role IN (:names)')
            ->andWhere('a.dateDebut < :now')
            ->andWhere('a.dateFin > :now OR a.dateFin IS NULL')
            ->setParameter('names', $names)
            ->setParameter('now', $now)
            ->getQuery()->getResult();

        $usersByMembre = $this->usersByMembreId($attributions);

        foreach ($attributions as $a) {
            $u = $usersByMembre[$a->getMembre()?->getId()] ?? null;
            if ($u === null) {
                continue;
            }
            $uid = spl_object_id($u);
            $held = $this->filterToAncestors($a->getFonction()->getRoles(), $names);
            $byUser[$uid] ??= ['user' => $u, 'grants' => []];
            $byUser[$uid]['grants'][] = new AccessGrant(
                provenance: Provenance::FONCTION_ROLE,
                sourceFonction: $a->getFonction(),
                roles: $held,
                explicitRoleNames: $this->roleNames($held),
                scope: $a->getGroupe(),
                sourceId: $a->getId(),
            );
        }

        // 3. Autorisation holders. Alias 'or' is reserved in DQL; use 'aor'.
        $autoRepo = $this->em->getRepository($this->secureConfig->getAutorisationClass());
        $autorisations = $autoRepo->createQueryBuilder('o')
            ->innerJoin('o.roles', 'aor')
            ->where('aor.role IN (:names)')
            ->setParameter('names', $names)
            ->getQuery()->getResult();

        foreach ($autorisations as $o) {
            $u = $o->getUser();
            if ($u === null) {
                continue;
            }
            $held = $this->filterToAncestors($o->getRoles()->toArray(), $names);
            $uid = spl_object_id($u);
            $byUser[$uid] ??= ['user' => $u, 'grants' => []];
            $byUser[$uid]['grants'][] = new AccessGrant(
                provenance: Provenance::AUTORISATION,
                sourceFonction: null,
                roles: $held,
                explicitRoleNames: $this->roleNames($held),
                scope: $o->getGroupe(),
                sourceId: $o->getId(),
            );
        }

        return $byUser;
    }

    /** @param array<int, array{user: BaseUser, grants: list<AccessGrant>}> $byUser */
    private function toEntries(array $byUser): array
    {
        return array_map(
            fn($row) => new ScopeAccessEntry($row['user'], $row['grants']),
            array_values($byUser),
        );
    }

    /**
     * @param  iterable<BaseRole> $roles
     * @param  string[]           $ancestorNames
     * @return BaseRole[]
     */
    private function filterToAncestors(iterable $roles, array $ancestorNames): array
    {
        $out = [];
        foreach ($roles as $r) {
            if (in_array($r->getRole(), $ancestorNames, true)) {
                $out[] = $r;
            }
        }
        return $out;
    }

    private function auditGroupeScope(BaseGroupe $groupe): ScopeAccessReport
    {
        // Walk parent chain: leaf -> ... -> root.
        $chain = [];
        for ($g = $groupe; $g !== null; $g = $g->getParent()) {
            $chain[] = $g;
        }

        /** @var array<int, array{user: BaseUser, grants: list<AccessGrant>}> $byUser */
        $byUser = [];

        foreach ($this->findAutorisationsForGroupes($chain) as $autorisation) {
            $u = $autorisation->getUser();
            if ($u === null) {
                continue;
            }
            $uid = spl_object_id($u);
            $byUser[$uid] ??= ['user' => $u, 'grants' => []];
            $byUser[$uid]['grants'][] = new AccessGrant(
                provenance: Provenance::AUTORISATION,
                sourceFonction: null,
                roles: $this->expand($autorisation->getRoles()),
                explicitRoleNames: $this->roleNames($autorisation->getRoles()),
                scope: $autorisation->getGroupe(),
                sourceId: $autorisation->getId(),
            );
        }

        $chainAttributions = $this->findAttributionsForGroupes($chain);
        $usersByMembre = $this->usersByMembreId($chainAttributions);

        foreach ($chainAttributions as $attribution) {
            $u = $usersByMembre[$attribution->getMembre()?->getId()] ?? null;
            if ($u === null) {
                continue;
            }
            $fonction = $attribution->getFonction();
            $uid = spl_object_id($u);
            $byUser[$uid] ??= ['user' => $u, 'grants' => []];
            $byUser[$uid]['grants'][] = new AccessGrant(
                provenance: Provenance::FONCTION_ROLE,
                sourceFonction: $fonction,
                roles: $this->expand($fonction->getRoles()),
                explicitRoleNames: $this->roleNames($fonction->getRoles()),
                scope: $attribution->getGroupe(),
                sourceId: $attribution->getId(),
            );
        }

        // Global-override holders: anyone with ROLE_ADMIN, ROLE_SG, or a
        // ROLE_*_EVERYWHERE can access every group, including this one, even
        // without an autorisation or attribution on the parent chain.
        foreach ($this->collectAccessForRoleNames($this->globalOverrideAncestorNames()) as $uid => $row) {
            $byUser[$uid] ??= ['user' => $row['user'], 'grants' => []];
            foreach ($row['grants'] as $g) {
                $byUser[$uid]['grants'][] = $g;
            }
        }

        return new ScopeAccessReport($groupe, $this->toEntries($byUser));
    }

    /**
     * Names of the global-override roles plus all their ancestors. An ancestor
     * grants the override role via children expansion in BaseUser::getAllRoles,
     * so a user holding an ancestor effectively holds the override.
     *
     * @return string[]
     */
    private function globalOverrideAncestorNames(): array
    {
        $roleRepo = $this->em->getRepository($this->secureConfig->getRoleClass());
        if ($roleRepo === null) {
            return [];
        }
        $names = [];
        foreach (self::GLOBAL_OVERRIDE_ROLES as $name) {
            $role = $roleRepo->findOneBy(['role' => $name]);
            if ($role === null) {
                continue;
            }
            for ($r = $role; $r !== null; $r = $r->getParent()) {
                $names[$r->getRole()] = true;
            }
        }
        return array_keys($names);
    }

    /** @param BaseGroupe[] $groupes */
    private function findAutorisationsForGroupes(array $groupes): iterable
    {
        if ($this->autorisationFinder !== null) {
            return ($this->autorisationFinder)($groupes);
        }
        if (empty($groupes)) {
            return [];
        }
        $repo = $this->em->getRepository($this->secureConfig->getAutorisationClass());
        return $repo->createQueryBuilder('o')
            ->where('o.groupe IN (:groupes)')
            ->setParameter('groupes', $groupes)
            ->getQuery()->getResult();
    }

    /**
     * Looks up users by their membre id. BaseMembre has no inverse pointer to
     * User, so we query in one batch and return a map keyed by membre id.
     *
     * @param  iterable<\NetBS\FichierBundle\Mapping\BaseAttribution> $attributions
     * @return array<int, BaseUser>
     */
    private function usersByMembreId(iterable $attributions): array
    {
        $membreIds = [];
        foreach ($attributions as $a) {
            $m = $a->getMembre();
            if ($m !== null) {
                $membreIds[$m->getId()] = true;
            }
        }
        if (empty($membreIds)) {
            return [];
        }
        $repo = $this->em->getRepository($this->secureConfig->getUserClass());
        $users = $repo->createQueryBuilder('u')
            ->where('IDENTITY(u.membre) IN (:ids)')
            ->setParameter('ids', array_keys($membreIds))
            ->getQuery()->getResult();

        $map = [];
        foreach ($users as $u) {
            $m = $u->getMembre();
            if ($m !== null) {
                $map[$m->getId()] = $u;
            }
        }
        return $map;
    }

    /** @param BaseGroupe[] $groupes */
    private function findAttributionsForGroupes(array $groupes): iterable
    {
        if ($this->attributionFinder !== null) {
            return ($this->attributionFinder)($groupes);
        }
        if (empty($groupes)) {
            return [];
        }
        $now = new \DateTime();
        $repo = $this->em->getRepository($this->fichierConfig->getAttributionClass());
        return $repo->createQueryBuilder('a')
            ->addSelect('f', 'fr')
            ->innerJoin('a.fonction', 'f')
            ->leftJoin('f.roles', 'fr')
            ->where('a.groupe IN (:groupes)')
            ->andWhere('a.dateDebut < :now')
            ->andWhere('a.dateFin > :now OR a.dateFin IS NULL')
            ->setParameter('groupes', $groupes)
            ->setParameter('now', $now)
            ->getQuery()->getResult();
    }
}
