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
        'ROLE_TRESORIER',
        'ROLE_APMBS',
        'ROLE_READ_EVERYWHERE',
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

        /** @var array<int, array{user: BaseUser, grants: list<AccessGrant>}> $byUser */
        $byUser = [];

        // 1. Direct holders.
        $userRepo = $this->em->getRepository($this->secureConfig->getUserClass());
        $directHolders = $userRepo->createQueryBuilder('u')
            ->innerJoin('u.roles', 'r')
            ->where('r.role IN (:names)')
            ->setParameter('names', $ancestorNames)
            ->distinct()
            ->getQuery()->getResult();

        foreach ($directHolders as $u) {
            $byUser[spl_object_id($u)] = [
                'user'   => $u,
                'grants' => [new AccessGrant(
                    provenance: Provenance::DIRECT_ROLE,
                    sourceFonction: null,
                    roles: $this->filterToAncestors($u->getDirectRoles(), $ancestorNames),
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
            ->setParameter('names', $ancestorNames)
            ->setParameter('now', $now)
            ->getQuery()->getResult();

        foreach ($attributions as $a) {
            $u = $a->getMembre()?->getUser();
            if ($u === null) {
                continue;
            }
            $uid = spl_object_id($u);
            $byUser[$uid] ??= ['user' => $u, 'grants' => []];
            $byUser[$uid]['grants'][] = new AccessGrant(
                provenance: Provenance::FONCTION_ROLE,
                sourceFonction: $a->getFonction(),
                roles: $this->filterToAncestors($a->getFonction()->getRoles(), $ancestorNames),
                scope: $a->getGroupe(),
                sourceId: $a->getId(),
            );
        }

        // 3. Autorisation holders. Alias 'or' is reserved in DQL; use 'aor'.
        $autoRepo = $this->em->getRepository($this->secureConfig->getAutorisationClass());
        $autorisations = $autoRepo->createQueryBuilder('o')
            ->innerJoin('o.roles', 'aor')
            ->where('aor.role IN (:names)')
            ->setParameter('names', $ancestorNames)
            ->getQuery()->getResult();

        foreach ($autorisations as $o) {
            $u = $o->getUser();
            if ($u === null) {
                continue;
            }
            $uid = spl_object_id($u);
            $byUser[$uid] ??= ['user' => $u, 'grants' => []];
            $byUser[$uid]['grants'][] = new AccessGrant(
                provenance: Provenance::AUTORISATION,
                sourceFonction: null,
                roles: $this->filterToAncestors($o->getRoles()->toArray(), $ancestorNames),
                scope: $o->getGroupe(),
                sourceId: $o->getId(),
            );
        }

        $entries = array_map(
            fn($row) => new ScopeAccessEntry($row['user'], $row['grants']),
            array_values($byUser),
        );

        return new ScopeAccessReport($role, $entries);
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
                scope: $autorisation->getGroupe(),
                sourceId: $autorisation->getId(),
            );
        }

        foreach ($this->findAttributionsForGroupes($chain) as $attribution) {
            $u = $attribution->getMembre()?->getUser();
            if ($u === null) {
                continue;
            }
            $uid = spl_object_id($u);
            $byUser[$uid] ??= ['user' => $u, 'grants' => []];
            $byUser[$uid]['grants'][] = new AccessGrant(
                provenance: Provenance::FONCTION_ROLE,
                sourceFonction: $attribution->getFonction(),
                roles: $this->expand($attribution->getFonction()->getRoles()),
                scope: $attribution->getGroupe(),
                sourceId: $attribution->getId(),
            );
        }

        $entries = array_map(
            fn($row) => new ScopeAccessEntry($row['user'], $row['grants']),
            array_values($byUser),
        );

        return new ScopeAccessReport($groupe, $entries);
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
