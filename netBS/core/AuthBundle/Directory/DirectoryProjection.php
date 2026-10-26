<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Contract\DirectoryUserProviderInterface;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\BaseGroupeType;
use NetBS\FichierBundle\Service\FichierConfig;

/**
 * Computes what the LDAP directory should contain, from netBS data. This is
 * where the group rules live (previously the nextcloud_* SQL views).
 *
 *   - users: whatever the app's {@see DirectoryUserProviderInterface} lists
 *   - groups: one per groupe whose type is set to "crée un groupe" (Structure ›
 *     Types de groupes); members follow the type's "sous-groupes" mode, see
 *     {@see GroupeMemberResolver}. Membres without a directory user are dropped.
 *   - role groups: one per {@see ROLE_GROUPS} entry, always present, holding
 *     the directory users that hold the role ({@see DirectoryUser::$roles}).
 */
final class DirectoryProjection
{
    /** Roles published as directory groups (role => lldap group name). */
    public const ROLE_GROUPS = [
        'ROLE_NEXTCLOUD_ADMIN' => 'nextcloud-admins',
        'ROLE_WIKI_ADMIN' => 'wiki-admins',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FichierConfig $fichierConfig,
        private readonly DirectoryUserProviderInterface $userProvider,
        private readonly GroupeMemberResolver $resolver,
    ) {
    }

    /**
     * @throws DirectoryNotConfiguredException when no groupe would become a group
     */
    public function snapshot(): DirectorySnapshot
    {
        $users = $this->userProvider->directoryUsers();
        $groups = $this->groups(self::uidByMembre($users));
        if ($groups === []) {
            throw new DirectoryNotConfiguredException();
        }

        return new DirectorySnapshot($users, $groups, $this->roleGroups($users));
    }

    /**
     * Group names end up in DNs. Nextcloud escapes these characters in DNs
     * (`\28` for `(`, ...) and lldap can't find entries by such DNs, so they
     * are replaced.
     */
    private static function dnSafe(string $name): string
    {
        return trim(strtr($name, [
            '(' => '[', ')' => ']',
            ',' => ' ', '=' => '-', '+' => '-', '<' => ' ', '>' => ' ', ';' => ' ',
            '"' => "'", '#' => ' ', '*' => ' ', '\\' => ' ',
        ]));
    }

    /**
     * @param array<string, DirectoryUser> $users
     * @return array<string, DirectoryGroup>
     */
    private function roleGroups(array $users): array
    {
        $groups = [];
        foreach (self::ROLE_GROUPS as $role => $name) {
            $members = array_values(array_map(fn (DirectoryUser $user) => $user->uid, self::roleHolders($users, $role)));
            sort($members);
            $groups[$role] = new DirectoryGroup(null, $name, $members, $role);
        }
        return $groups;
    }

    /**
     * @param array<string, DirectoryUser> $users
     * @return array<int, string> membre id => uid
     */
    private static function uidByMembre(array $users): array
    {
        $uids = [];
        foreach ($users as $uid => $user) {
            if ($user->membreId !== null) {
                $uids[$user->membreId] = $uid;
            }
        }
        return $uids;
    }

    /**
     * @param array<string, DirectoryUser> $users
     * @return array<string, DirectoryUser> the users holding $role, keys kept
     */
    private static function roleHolders(array $users, string $role): array
    {
        return array_filter($users, fn (DirectoryUser $user) => in_array($role, $user->roles, true));
    }

    /**
     * The groupe groups' names and types, without their members: cheap, for
     * listing. Names are the ones {@see snapshot()} gives.
     *
     * @return array<int, array{name: string, typeName: string, sousGroupes: string}> keyed by groupe id
     * @throws DirectoryNotConfiguredException when no groupe would become a group
     */
    public function groupOutlines(): array
    {
        $groupes = $this->creeGroupeGroupes(false) ?: throw new DirectoryNotConfiguredException();
        $outlines = [];
        foreach (self::names($groupes) as $id => $name) {
            $type = $groupes[$id]->getGroupeType();
            $outlines[$id] = ['name' => $name, 'typeName' => $type->getNom(), 'sousGroupes' => $type->getSousGroupes()];
        }
        return $outlines;
    }

    /**
     * One group as {@see snapshot()} computes it, without resolving the others.
     *
     * @param string $key netBS groupe id, or a role of {@see ROLE_GROUPS}
     * @param array<string, DirectoryUser>|null $users the directory users, when
     *        the caller has them; otherwise loaded, as little as the group needs
     * @return DirectoryGroup|null null when $key matches no group
     */
    public function group(string $key, ?array $users = null): ?DirectoryGroup
    {
        if (!ctype_digit($key)) {
            return isset(self::ROLE_GROUPS[$key]) ? $this->roleGroups($users ?? $this->userProvider->directoryUsers())[$key] : null;
        }

        $groupes = $this->creeGroupeGroupes(false);
        $groupe = $groupes[(int) $key] ?? null;
        if ($groupe === null) {
            return null;
        }
        $this->prefetchSubtree($groupe);
        $uidByMembre = $users !== null ? self::uidByMembre($users) : $this->userProvider->uidsByMembre();
        return $this->buildGroup($groupe, self::names($groupes)[$groupe->getId()], $uidByMembre);
    }

    /**
     * @param array<int, string> $uidByMembre membre id => uid; membres without an account are dropped
     * @return array<int, DirectoryGroup>
     */
    private function groups(array $uidByMembre): array
    {
        $groupes = $this->creeGroupeGroupes(true);
        $this->prefetchSousGroupes($groupes);

        $groups = [];
        foreach (self::names($groupes) as $id => $name) {
            $groups[$id] = $this->buildGroup($groupes[$id], $name, $uidByMembre);
        }
        return $groups;
    }

    /** @param array<int, string> $uidByMembre */
    private function buildGroup(BaseGroupe $groupe, string $name, array $uidByMembre): DirectoryGroup
    {
        $members = [];
        foreach ($this->resolver->membreIds($groupe, $groupe->getGroupeType()->getSousGroupes()) as $membreId) {
            $uid = $uidByMembre[$membreId] ?? null;
            if ($uid !== null) {
                $members[$uid] = $uid;
            }
        }

        return new DirectoryGroup(
            $groupe->getId(),
            $name,
            array_values($members),
            typeName: $groupe->getGroupeType()->getNom(),
            sousGroupes: $groupe->getGroupeType()->getSousGroupes(),
        );
    }

    /**
     * The groupes whose type creates a group, by id.
     *
     * @return array<int, BaseGroupe>
     */
    private function creeGroupeGroupes(bool $withAttributions): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('g', 't')
            ->from($this->fichierConfig->getGroupeClass(), 'g', 'g.id')
            ->join('g.groupeType', 't')
            ->where('t.creeGroupe = true')
            ->orderBy('g.id');
        if ($withAttributions) {
            $qb->addSelect('a', 'am')
                ->leftJoin('g.attributions', 'a')
                ->leftJoin('a.membre', 'am');
        }
        return $qb->getQuery()->getResult();
    }

    /**
     * lldap group names are unique and two netBS groupes can share a nom; role
     * group names are reserved too. A clash gets the groupe id as suffix, so
     * names depend on every groupe, in id order.
     *
     * @param array<int, BaseGroupe> $groupes ordered by id
     * @return array<int, string> groupe id => name
     */
    private static function names(array $groupes): array
    {
        $names = [];
        $seen = array_fill_keys(array_map('mb_strtolower', self::ROLE_GROUPS), true);
        foreach ($groupes as $groupe) {
            $name = self::dnSafe(trim($groupe->getNom()));
            if (isset($seen[mb_strtolower($name)])) {
                $name = sprintf('%s [%d]', $name, $groupe->getId());
            }
            $seen[mb_strtolower($name)] = true;
            $names[$groupe->getId()] = $name;
        }
        return $names;
    }

    /**
     * Loads the attributions of $root and, in "tous" mode, of its whole
     * subtree, in at most two queries; the resolver then finds initialized
     * collections instead of lazy-loading each groupe.
     */
    private function prefetchSubtree(BaseGroupe $root): void
    {
        $groupes = [$root];
        if ($root->getGroupeType()->getSousGroupes() === BaseGroupeType::SOUS_GROUPES_TOUS) {
            $this->fetchEnfants();
            $groupes = [$root, ...$this->resolver->descendants($root)];
        }
        $this->fetchAttributions(array_map(fn (BaseGroupe $groupe) => $groupe->getId(), $groupes));
    }

    /**
     * Loads, in two queries, every groupe's enfants and attributions,
     * so {@see GroupeMemberResolver} walking a "tous" subtree
     * finds initialized collections instead of lazy-loading each groupe.
     *
     * @param BaseGroupe[] $groupes
     */
    private function prefetchSousGroupes(array $groupes): void
    {
        $needsTree = false;
        foreach ($groupes as $groupe) {
            if ($groupe->getGroupeType()->getSousGroupes() === BaseGroupeType::SOUS_GROUPES_TOUS) {
                $needsTree = true;
                break;
            }
        }
        if (!$needsTree) {
            return;
        }

        $this->fetchEnfants();
        $this->fetchAttributions(null);
    }

    // Fetch joins initialize the collections of groupes already managed.

    private function fetchEnfants(): void
    {
        $this->em->createQueryBuilder()
            ->select('g', 'e')
            ->from($this->fichierConfig->getGroupeClass(), 'g')
            ->leftJoin('g.enfants', 'e')
            ->getQuery()
            ->getResult();
    }

    /** @param int[]|null $groupeIds null for every groupe */
    private function fetchAttributions(?array $groupeIds): void
    {
        $qb = $this->em->createQueryBuilder()
            ->select('g', 'a', 'am')
            ->from($this->fichierConfig->getGroupeClass(), 'g')
            ->leftJoin('g.attributions', 'a')
            ->leftJoin('a.membre', 'am');
        if ($groupeIds !== null) {
            $qb->where('g.id IN (:ids)')->setParameter('ids', $groupeIds);
        }
        $qb->getQuery()->getResult();
    }
}
