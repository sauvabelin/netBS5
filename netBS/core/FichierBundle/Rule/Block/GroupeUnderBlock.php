<?php

namespace NetBS\FichierBundle\Rule\Block;

use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Rule\Ids;

/** True when a strict ancestor, at any depth, is one of the groupes. */
class GroupeUnderBlock implements RuleBlock
{
    public function id(): string
    {
        return 'groupe.sous';
    }

    public function contexts(): array
    {
        return ['groupe'];
    }

    public function label(): string
    {
        return 'le groupe est un sous-groupe de';
    }

    public function negatedLabel(): ?string
    {
        return "le groupe n'est pas un sous-groupe de";
    }

    public function params(): array
    {
        return ['groupes' => RuleParam::entities('groupe')];
    }

    public function describe(array $display, bool $not): string
    {
        return ($not ? "le groupe n'est pas un sous-groupe de " : 'le groupe est un sous-groupe de ') . $display['groupes'];
    }

    /** @param BaseGroupe $subject */
    public function matches(object $subject, array $params): bool
    {
        $visited = [spl_object_id($subject) => true];
        for ($groupe = $subject->getParent(); $groupe !== null && !isset($visited[spl_object_id($groupe)]); $groupe = $groupe->getParent()) {
            if (Ids::contains($params['groupes'] ?? [], $groupe)) {
                return true;
            }
            $visited[spl_object_id($groupe)] = true;
        }

        return false;
    }
}
