<?php

namespace NetBS\FichierBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\SecureBundle\Mapping\BaseUser;

final class MesUnitesResolver
{
    private const PALETTE = [
        '#4f9eff',
        '#ff9f43',
        '#6cd4a0',
        '#c878ff',
        '#ff7b8a',
        '#4ed4d4',
        '#ffd95a',
        '#ff6b6b',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FichierConfig $config,
    ) {}

    /**
     * @return MesUnitesRoot[]
     */
    public function resolveFor(BaseUser $user): array
    {
        $membre = $user->getMembre();
        if ($membre === null) {
            return [];
        }

        $attributions = $membre->getActivesAttributions();
        if (count($attributions) === 0) {
            return [];
        }

        $groupsById = [];
        $userFonctionsByGroupId = [];
        foreach ($attributions as $attr) {
            $g = $attr->getGroupe();
            if ($g === null) {
                continue;
            }
            $id = $g->getId();
            $groupsById[$id] = $g;
            $userFonctionsByGroupId[$id] = (string) $attr->getFonction();
        }

        $roots = array_values($groupsById);
        usort($roots, self::compareGroupsByName(...));

        $now = new \DateTimeImmutable();

        return array_map(
            fn(BaseGroupe $root) => $this->buildRoot($root, $userFonctionsByGroupId, $now),
            $roots,
        );
    }

    private function buildRoot(BaseGroupe $root, array $userFonctionsByGroupId, \DateTimeImmutable $now): MesUnitesRoot
    {
        $rootSubtree = $this->subtreeIds($root);
        $membersByGroup = $this->fetchMembersByGroup($rootSubtree, $now);

        $childGroups = $root->getEnfants()->toArray();
        usort($childGroups, self::compareGroupsByName(...));

        $palette = self::PALETTE;
        $paletteSize = count($palette);
        $children = [];
        foreach ($childGroups as $i => $child) {
            $children[] = new MesUnitesChild(
                group: $child,
                activeMembers: self::countMembersIn($this->subtreeIds($child), $membersByGroup),
                userFonction: $userFonctionsByGroupId[$child->getId()] ?? null,
                color: $palette[$i % $paletteSize],
            );
        }

        return new MesUnitesRoot(
            group: $root,
            totalActiveMembers: self::countMembersIn($rootSubtree, $membersByGroup),
            children: $children,
        );
    }

    /**
     * @return int[]
     */
    private function subtreeIds(BaseGroupe $group): array
    {
        $ids = [$group->getId()];
        foreach ($group->getEnfantsRecursive() as $d) {
            $ids[] = $d->getId();
        }
        return $ids;
    }

    /**
     * Single query: fetch all (groupId, memberId) pairs for active attributions
     * inside the given group ids. Returns a map [groupId => [memberId => true, ...]]
     * so that subtree counts can be computed in PHP without further DB round-trips.
     *
     * @param int[] $groupIds
     * @return array<int, array<int, true>>
     */
    private function fetchMembersByGroup(array $groupIds, \DateTimeImmutable $now): array
    {
        if (count($groupIds) === 0) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(a.groupe) AS gid', 'IDENTITY(a.membre) AS mid')
            ->from($this->config->getAttributionClass(), 'a')
            ->where('a.groupe IN (:gids)')
            ->andWhere('a.dateDebut <= :now')
            ->andWhere('(a.dateFin IS NULL OR a.dateFin >= :now)')
            ->setParameter('gids', $groupIds)
            ->setParameter('now', $now)
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['gid']][(int) $row['mid']] = true;
        }
        return $map;
    }

    /**
     * @param int[] $subtreeIds
     * @param array<int, array<int, true>> $membersByGroup
     */
    private static function countMembersIn(array $subtreeIds, array $membersByGroup): int
    {
        $members = [];
        foreach ($subtreeIds as $gid) {
            if (isset($membersByGroup[$gid])) {
                $members += $membersByGroup[$gid];
            }
        }
        return count($members);
    }

    private static function compareGroupsByName(BaseGroupe $a, BaseGroupe $b): int
    {
        return strcasecmp((string) $a->getNom(), (string) $b->getNom());
    }
}
