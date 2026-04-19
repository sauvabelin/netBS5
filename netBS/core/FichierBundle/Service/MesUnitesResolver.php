<?php

namespace NetBS\FichierBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\SecureBundle\Mapping\BaseUser;

final class MesUnitesResolver
{
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
        foreach ($attributions as $attr) {
            $g = $attr->getGroupe();
            if ($g !== null) {
                $groupsById[$g->getId()] = $g;
            }
        }

        $roots = $this->filterOutDescendants($groupsById);

        usort($roots, static fn(BaseGroupe $a, BaseGroupe $b) =>
            strcasecmp((string) $a->getNom(), (string) $b->getNom())
        );

        $userFonctionsByGroupId = [];
        foreach ($attributions as $attr) {
            $g = $attr->getGroupe();
            if ($g !== null) {
                $userFonctionsByGroupId[$g->getId()] = (string) $attr->getFonction();
            }
        }

        $result = [];
        foreach ($roots as $root) {
            $result[] = $this->buildRoot($root, $userFonctionsByGroupId);
        }

        return $result;
    }

    /**
     * Drop every group that has an ancestor (via getParent()) also present in $groupsById.
     *
     * @param array<int, BaseGroupe> $groupsById
     * @return BaseGroupe[]
     */
    private function filterOutDescendants(array $groupsById): array
    {
        $roots = [];
        foreach ($groupsById as $id => $group) {
            $ancestor = $group->getParent();
            $hasAncestorInSet = false;
            while ($ancestor !== null) {
                if (isset($groupsById[$ancestor->getId()])) {
                    $hasAncestorInSet = true;
                    break;
                }
                $ancestor = $ancestor->getParent();
            }
            if (!$hasAncestorInSet) {
                $roots[] = $group;
            }
        }
        return $roots;
    }

    /**
     * @param array<int, string> $userFonctionsByGroupId
     */
    private function buildRoot(BaseGroupe $root, array $userFonctionsByGroupId): MesUnitesRoot
    {
        $descendants = $root->getEnfantsRecursive();
        $subtreeIds = [$root->getId()];
        foreach ($descendants as $d) {
            $subtreeIds[] = $d->getId();
        }

        $totalActiveMembers = $this->countDistinctActiveMembers($subtreeIds);

        $children = [];
        foreach ($root->getEnfants() as $child) {
            $activeCount = $this->countDistinctActiveMembers([$child->getId()]);
            $children[] = new MesUnitesChild(
                group: $child,
                activeMembers: $activeCount,
                userFonction: $userFonctionsByGroupId[$child->getId()] ?? null,
            );
        }

        usort($children, static fn(MesUnitesChild $a, MesUnitesChild $b) =>
            strcasecmp((string) $a->group->getNom(), (string) $b->group->getNom())
        );

        return new MesUnitesRoot(
            group: $root,
            totalActiveMembers: $totalActiveMembers,
            children: $children,
        );
    }

    /**
     * Count distinct active-attribution members across the given group ids.
     *
     * @param int[] $groupIds
     */
    private function countDistinctActiveMembers(array $groupIds): int
    {
        if (count($groupIds) === 0) {
            return 0;
        }

        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(DISTINCT m.id)')
            ->from($this->config->getAttributionClass(), 'a')
            ->join('a.membre', 'm')
            ->where('a.groupe IN (:gids)')
            ->andWhere('a.dateDebut <= :now')
            ->andWhere('(a.dateFin IS NULL OR a.dateFin >= :now)')
            ->setParameter('gids', $groupIds)
            ->setParameter('now', new \DateTime());

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
