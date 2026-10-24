<?php

declare(strict_types=1);

namespace App\Ldap;

use App\Entity\BSGroupe;
use App\Entity\BSUser;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Computes what the LDAP directory should contain, from netBS data. This is
 * where the group rules live (previously the nextcloud_* SQL views).
 *
 *   - users: active accounts with nextcloud_account = 1
 *   - groups: one per groupe whose type is set to "crée un groupe" (Structure ›
 *     Types de groupes); members follow the type's "sous-groupes" mode, see
 *     {@see GroupeMemberResolver}. Membres without a directory user are dropped.
 */
final class DirectoryProjection
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly GroupeMemberResolver $resolver,
    ) {
    }

    /**
     * @return array{users: array<string, DirectoryUser>, groups: array<int, DirectoryGroup>}
     *               users keyed by uid, groups keyed by netBS groupe id
     *
     * @throws DirectoryNotConfiguredException when no groupe would become a group
     */
    public function snapshot(): array
    {
        /** @var BSUser[] $accounts */
        $accounts = $this->em->createQueryBuilder()
            ->select('u', 'm')
            ->from(BSUser::class, 'u')
            ->leftJoin('u.membre', 'm')
            ->where('u.nextcloudAccount = true')
            ->andWhere('u.isActive = true')
            ->getQuery()
            ->getResult();

        $users = [];
        $uidByMembre = [];
        foreach ($accounts as $account) {
            $membre = $account->getMembre();
            $uid = mb_strtolower($account->getUsername());
            $users[$uid] = new DirectoryUser(
                uid: $uid,
                email: $this->email($account, $uid),
                displayName: $membre ? trim($membre->getFullName()) : $account->getUsername(),
                firstName: $membre ? (string) $membre->getPrenom() : '',
                lastName: $membre ? (string) $membre->_getNom() : '',
            );
            if ($membre !== null) {
                $uidByMembre[$membre->getId()] = $uid;
            }
        }

        $groups = $this->groups($uidByMembre);
        if ($groups === []) {
            throw new DirectoryNotConfiguredException();
        }

        return ['users' => $users, 'groups' => $groups];
    }

    /**
     * @param array<int, string> $uidByMembre membre id => uid; membres without an account are dropped
     * @return array<int, DirectoryGroup>
     */
    private function groups(array $uidByMembre): array
    {
        /** @var BSGroupe[] $groupes */
        $groupes = $this->em->createQueryBuilder()
            ->select('g', 't', 'a', 'am')
            ->from(BSGroupe::class, 'g')
            ->join('g.groupeType', 't')
            ->leftJoin('g.attributions', 'a')
            ->leftJoin('a.membre', 'am')
            ->where('t.creeGroupe = true')
            ->orderBy('g.id')
            ->getQuery()
            ->getResult();

        $groups = [];
        $seenNames = [];
        foreach ($groupes as $groupe) {
            $members = [];
            foreach ($this->resolver->membreIds($groupe, $groupe->getGroupeType()->getSousGroupes()) as $membreId) {
                $uid = $uidByMembre[$membreId] ?? null;
                if ($uid !== null) {
                    $members[$uid] = $uid;
                }
            }

            // lldap group names are unique; two netBS groupes can share a nom.
            $name = trim($groupe->getNom());
            if (isset($seenNames[mb_strtolower($name)])) {
                $name = sprintf('%s (%d)', $name, $groupe->getId());
            }
            $seenNames[mb_strtolower($name)] = true;

            $groups[$groupe->getId()] = new DirectoryGroup($groupe->getId(), $name, array_values($members));
        }
        return $groups;
    }

    /**
     * lldap refuses users without an email, and most netBS accounts have none.
     * Placeholder addresses use the reserved .invalid TLD so nothing is ever
     * delivered to them.
     */
    private function email(BSUser $account, string $uid): string
    {
        return $account->getEmail() ?: $account->getEmailBS() ?: sprintf('%s@no-email.invalid', $uid);
    }
}
