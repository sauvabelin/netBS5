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
 */
final class DirectoryProjection
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FichierConfig $fichierConfig,
        private readonly DirectoryUserProviderInterface $userProvider,
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

        return ['users' => $users, 'groups' => $groups];
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
}
