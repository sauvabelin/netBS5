<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Contract\DirectoryUserProviderInterface;
use NetBS\FichierBundle\Mapping\BaseGroupe;
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
     * @return array{users: array<string, DirectoryUser>, groups: array<int, DirectoryGroup>, roleGroups: array<string, DirectoryGroup>}
     *               users keyed by uid, groups keyed by netBS groupe id, roleGroups keyed by role
     *
     * @throws DirectoryNotConfiguredException when no groupe would become a group
     */
    public function snapshot(): array
    {
        $users = $this->userProvider->directoryUsers();
        $uidByMembre = [];
        foreach ($users as $uid => $user) {
            if ($user->membreId !== null) {
                $uidByMembre[$user->membreId] = $uid;
            }
        }

        $groups = $this->groups($uidByMembre);
        if ($groups === []) {
            throw new DirectoryNotConfiguredException();
        }

        return ['users' => $users, 'groups' => $groups, 'roleGroups' => $this->roleGroups($users)];
    }

    /**
     * Members of one group, computed without building the whole snapshot.
     *
     * @param string $key netBS groupe id, or a {@see ROLE_GROUPS} role
     * @return list<DirectoryUser>|null sorted by display name; null when $key
     *                                  is not a group of the directory
     */
    public function members(string $key): ?array
    {
        if (isset(self::ROLE_GROUPS[$key])) {
            $members = array_filter($this->userProvider->directoryUsers(), fn (DirectoryUser $user) => in_array($key, $user->roles, true));
        } elseif (ctype_digit($key)) {
            /** @var BaseGroupe|null $groupe */
            $groupe = $this->em->find($this->fichierConfig->getGroupeClass(), (int) $key);
            if ($groupe === null || !$groupe->getGroupeType()->isCreeGroupe()) {
                return null;
            }
            $membreIds = array_flip($this->resolver->membreIds($groupe, $groupe->getGroupeType()->getSousGroupes()));
            $members = array_filter($this->userProvider->directoryUsers(), fn (DirectoryUser $user) => isset($membreIds[$user->membreId]));
        } else {
            return null;
        }

        usort($members, fn (DirectoryUser $a, DirectoryUser $b) => strcasecmp($a->displayName, $b->displayName));
        return $members;
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
            $members = [];
            foreach ($users as $user) {
                if (in_array($role, $user->roles, true)) {
                    $members[] = $user->uid;
                }
            }
            sort($members);
            $groups[$role] = new DirectoryGroup(null, $name, $members, $role);
        }
        return $groups;
    }

    /**
     * @param array<int, string> $uidByMembre membre id => uid; membres without an account are dropped
     * @return array<int, DirectoryGroup>
     */
    private function groups(array $uidByMembre): array
    {
        /** @var BaseGroupe[] $groupes */
        $groupes = $this->em->createQueryBuilder()
            ->select('g', 't', 'a', 'am')
            ->from($this->fichierConfig->getGroupeClass(), 'g')
            ->join('g.groupeType', 't')
            ->leftJoin('g.attributions', 'a')
            ->leftJoin('a.membre', 'am')
            ->where('t.creeGroupe = true')
            ->orderBy('g.id')
            ->getQuery()
            ->getResult();

        $groups = [];
        // Role group names are reserved; a groupe using one gets the id suffix.
        $seenNames = array_fill_keys(array_map('mb_strtolower', self::ROLE_GROUPS), true);
        foreach ($groupes as $groupe) {
            $members = [];
            foreach ($this->resolver->membreIds($groupe, $groupe->getGroupeType()->getSousGroupes()) as $membreId) {
                $uid = $uidByMembre[$membreId] ?? null;
                if ($uid !== null) {
                    $members[$uid] = $uid;
                }
            }

            // lldap group names are unique; two netBS groupes can share a nom.
            $name = self::dnSafe(trim($groupe->getNom()));
            if (isset($seenNames[mb_strtolower($name)])) {
                $name = sprintf('%s [%d]', $name, $groupe->getId());
            }
            $seenNames[mb_strtolower($name)] = true;

            $groups[$groupe->getId()] = new DirectoryGroup($groupe->getId(), $name, array_values($members));
        }
        return $groups;
    }
}
