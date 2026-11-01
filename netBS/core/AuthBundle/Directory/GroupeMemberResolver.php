<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

use NetBS\AuthBundle\Entity\DirectoryPublicationRule;
use NetBS\CoreBundle\Rule\RuleEvaluationException;
use NetBS\CoreBundle\Rule\RuleEvaluator;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Rule\AttributionInSousGroupe;

/**
 * Turns a groupe and a "sous-groupes" mode (see DirectoryPublicationRule::SOUS_GROUPES_*)
 * into the ids of its members:
 *   - direct: active attributions in the groupe itself
 *   - tous:   plus every active attribution in any sub-groupe, at any depth
 *   - regle:  plus every active attribution in any sub-groupe that matches the
 *             rule (context attribution_sous_groupe); no rule = own members only
 */
final class GroupeMemberResolver
{
    public function __construct(private readonly RuleEvaluator $evaluator)
    {
    }

    /**
     * @param string|null $rule rule JSON, for the "regle" mode
     * @return int[] unique membre ids
     * @throws RuleEvaluationException when the rule is broken or fails for an attribution
     */
    public function membreIds(BaseGroupe $groupe, string $sousGroupes, ?string $rule = null): array
    {
        if (!in_array($sousGroupes, DirectoryPublicationRule::getSousGroupesChoices(), true)) {
            throw new \InvalidArgumentException(sprintf('Unknown sous-groupes mode "%s".', $sousGroupes));
        }

        $ids = [];
        foreach ($groupe->getActivesAttributions() as $attribution) {
            $ids[$attribution->getMembre()->getId()] = true;
        }

        if ($sousGroupes === DirectoryPublicationRule::SOUS_GROUPES_TOUS) {
            foreach ($this->descendants($groupe) as $descendant) {
                foreach ($descendant->getActivesAttributions() as $attribution) {
                    $ids[$attribution->getMembre()->getId()] = true;
                }
            }
        }

        if ($sousGroupes === DirectoryPublicationRule::SOUS_GROUPES_REGLE && $rule !== null) {
            foreach ($this->descendantsWithDepth($groupe) as [$descendant, $depth]) {
                foreach ($descendant->getActivesAttributions() as $attribution) {
                    if ($this->evaluator->matchesStrict($rule, 'attribution_sous_groupe', new AttributionInSousGroupe($attribution, $depth))) {
                        $ids[$attribution->getMembre()->getId()] = true;
                    }
                }
            }
        }

        return array_keys($ids);
    }

    /**
     * Every sub-groupe with its depth (1 = direct child). Breadth-first, each
     * groupe once at its smallest depth, so a parent cycle terminates.
     *
     * @return list<array{BaseGroupe, int}>
     */
    public function descendantsWithDepth(BaseGroupe $root): array
    {
        $visited = [$this->key($root) => true];
        $result = [];
        $level = [$root];

        for ($depth = 1; $level !== []; $depth++) {
            $next = [];
            foreach ($level as $groupe) {
                foreach ($groupe->getEnfants() as $enfant) {
                    $key = $this->key($enfant);
                    if (isset($visited[$key])) {
                        continue;
                    }
                    $visited[$key] = true;
                    $result[] = [$enfant, $depth];
                    $next[] = $enfant;
                }
            }
            $level = $next;
        }

        return $result;
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
