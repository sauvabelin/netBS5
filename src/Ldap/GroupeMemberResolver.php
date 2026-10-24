<?php

declare(strict_types=1);

namespace App\Ldap;

use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\BaseGroupeType;

/**
 * Turns a groupe and a "sous-groupes" mode (see BaseGroupeType::SOUS_GROUPES_*)
 * into the ids of its members:
 *   - direct:    active attributions in the groupe itself
 *   - fonctions: plus active attributions in any sub-groupe whose fonction
 *                counts in the parent groupe (CP, CL, …)
 *   - tous:      plus every active attribution in any sub-groupe
 *
 * Sub-groupes are all descendants, at any depth. Not LDAP-specific: meant to
 * be shared with mailing lists later.
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

        if ($sousGroupes !== BaseGroupeType::SOUS_GROUPES_DIRECT) {
            $onlyMarked = $sousGroupes === BaseGroupeType::SOUS_GROUPES_FONCTIONS;
            foreach ($this->descendants($groupe) as $descendant) {
                foreach ($descendant->getActivesAttributions() as $attribution) {
                    if (!$onlyMarked || $attribution->getFonction()->isCompteDansParent()) {
                        $ids[$attribution->getMembre()->getId()] = true;
                    }
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * Depth-first, each groupe once, so a parent cycle in the data terminates.
     *
     * @return BaseGroupe[]
     */
    private function descendants(BaseGroupe $root): array
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
