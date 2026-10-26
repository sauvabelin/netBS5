<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\BaseGroupeType;

/**
 * Turns a groupe and a "sous-groupes" mode (see BaseGroupeType::SOUS_GROUPES_*)
 * into the ids of its members:
 *   - direct: active attributions in the groupe itself
 *   - tous:   plus every active attribution in any sub-groupe, at any depth
 */
final class GroupeMemberResolver
{
    /**
     * @return int[] unique membre ids
     */
    public function membreIds(BaseGroupe $groupe, string $sousGroupes): array
    {
        if (!in_array($sousGroupes, BaseGroupeType::getSousGroupesChoices(), true)) {
            throw new \InvalidArgumentException(sprintf('Unknown sous-groupes mode "%s".', $sousGroupes));
        }

        $ids = [];
        foreach ($groupe->getActivesAttributions() as $attribution) {
            $ids[$attribution->getMembre()->getId()] = true;
        }

        if ($sousGroupes === BaseGroupeType::SOUS_GROUPES_TOUS) {
            foreach ($this->descendants($groupe) as $descendant) {
                foreach ($descendant->getActivesAttributions() as $attribution) {
                    $ids[$attribution->getMembre()->getId()] = true;
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * Every sub-groupe, at any depth. Depth-first, each groupe once, so a
     * parent cycle in the data terminates.
     *
     * @return BaseGroupe[]
     */
    public function descendants(BaseGroupe $root): array
    {
        $visited = [$this->key($root) => true];
        $result = [];
        $stack = [$root];

        while ($stack !== []) {
            foreach (array_pop($stack)->getEnfants() as $enfant) {
                $key = $this->key($enfant);
                if (isset($visited[$key])) {
                    continue;
                }
                $visited[$key] = true;
                $result[] = $enfant;
                $stack[] = $enfant;
            }
        }

        return $result;
    }

    private function key(BaseGroupe $groupe): string
    {
        return $groupe->getId() !== null ? 'id:' . $groupe->getId() : 'obj:' . spl_object_id($groupe);
    }
}
