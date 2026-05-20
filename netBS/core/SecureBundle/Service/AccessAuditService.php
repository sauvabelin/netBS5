<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Service;

use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\SecureBundle\Audit\AccessGrant;
use NetBS\SecureBundle\Audit\Provenance;
use NetBS\SecureBundle\Audit\ScopeAccessEntry;
use NetBS\SecureBundle\Audit\ScopeAccessReport;
use NetBS\SecureBundle\Audit\UserAccessReport;
use NetBS\SecureBundle\Mapping\BaseRole;
use NetBS\SecureBundle\Mapping\BaseUser;

final class AccessAuditService
{
    /** @var \Closure(BaseGroupe): iterable|null  Test seam: returns Autorisation[] for a Groupe. */
    private ?\Closure $autorisationFinder = null;

    /** @var \Closure(BaseGroupe): iterable|null  Test seam: returns Attribution[] for a Groupe. */
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

    public function auditScope(BaseGroupe|BaseRole $scope): ScopeAccessReport
    {
        if ($scope instanceof BaseGroupe) {
            return $this->auditGroupeScope($scope);
        }
        return $this->auditRoleScope($scope);
    }

    private function auditRoleScope(BaseRole $role): ScopeAccessReport
    {
        // All role names this scope covers (role + descendants).
        $roleNames = array_values(array_unique(array_map(
            fn(BaseRole $r) => $r->getRole(),
            $role->getChildrenRecursive(),
        )));

        /** @var array<int, array{user: BaseUser, grants: list<AccessGrant>}> $byUser */
        $byUser = [];

        // 1. Direct holders.
        $userClass = $this->secureConfig->getUserClass();
        $userRepo = $this->em->getRepository($userClass);
        $directHolders = $userRepo->createQueryBuilder('u')
            ->innerJoin('u.roles', 'r')
            ->where('r.role IN (:names)')
            ->setParameter('names', $roleNames)
            ->distinct()
            ->getQuery()->getResult();

        foreach ($directHolders as $u) {
            $byUser[spl_object_id($u)] = [
                'user'   => $u,
                'grants' => [new AccessGrant(
                    provenance: Provenance::DIRECT_ROLE,
                    sourceFonction: null,
                    roles: [$role],
                    scope: null,
                )],
            ];
        }

        // 2. Fonction holders (via active attributions).
        $now = new \DateTime();
        $attrClass = $this->fichierConfig->getAttributionClass();
        $attrRepo = $this->em->getRepository($attrClass);
        $attributions = $attrRepo->createQueryBuilder('a')
            ->innerJoin('a.fonction', 'f')
            ->innerJoin('f.roles', 'fr')
            ->where('fr.role IN (:names)')
            ->andWhere('a.dateDebut <= :now')
            ->andWhere('a.dateFin >= :now OR a.dateFin IS NULL')
            ->setParameter('names', $roleNames)
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
                roles: [$role],
                scope: $a->getGroupe(),
                sourceId: $a->getId(),
            );
        }

        // 3. Autorisation holders.
        $autoClass = $this->secureConfig->getAutorisationClass();
        $autoRepo = $this->em->getRepository($autoClass);
        // Alias 'or' is a reserved keyword in DQL — use 'aor' instead.
        $autorisations = $autoRepo->createQueryBuilder('o')
            ->innerJoin('o.roles', 'aor')
            ->where('aor.role IN (:names)')
            ->setParameter('names', $roleNames)
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
                roles: [$role],
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

    private function auditGroupeScope(BaseGroupe $groupe): ScopeAccessReport
    {
        // Walk parent chain: leaf -> ... -> root.
        $chain = [];
        for ($g = $groupe; $g !== null; $g = $g->getParent()) {
            $chain[] = $g;
        }

        /** @var array<int, array{user: BaseUser, grants: list<AccessGrant>}> $byUser */
        $byUser = [];

        foreach ($chain as $g) {
            foreach ($this->findAutorisationsForGroupe($g) as $autorisation) {
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

            foreach ($this->findAttributionsForGroupe($g) as $attribution) {
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
        }

        $entries = array_map(
            fn($row) => new ScopeAccessEntry($row['user'], $row['grants']),
            array_values($byUser),
        );

        return new ScopeAccessReport($groupe, $entries);
    }

    private function findAutorisationsForGroupe(BaseGroupe $g): iterable
    {
        if ($this->autorisationFinder !== null) {
            return ($this->autorisationFinder)($g);
        }
        $repo = $this->em->getRepository($this->secureConfig->getAutorisationClass());
        return $repo->findBy(['groupe' => $g]);
    }

    private function findAttributionsForGroupe(BaseGroupe $g): iterable
    {
        if ($this->attributionFinder !== null) {
            return ($this->attributionFinder)($g);
        }
        $now = new \DateTime();
        $class = $this->fichierConfig->getAttributionClass();
        $repo = $this->em->getRepository($class);
        return $repo->createQueryBuilder('a')
            ->where('a.groupe = :g')
            ->andWhere('a.dateDebut <= :now')
            ->andWhere('a.dateFin >= :now OR a.dateFin IS NULL')
            ->setParameter('g', $g)
            ->setParameter('now', $now)
            ->getQuery()->getResult();
    }
}
