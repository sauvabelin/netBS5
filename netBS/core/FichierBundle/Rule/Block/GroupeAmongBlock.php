<?php

namespace NetBS\FichierBundle\Rule\Block;

use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Rule\Ids;

class GroupeAmongBlock implements RuleBlock
{
    public function id(): string
    {
        return 'groupe.parmi';
    }

    public function contexts(): array
    {
        return ['groupe'];
    }

    public function label(): string
    {
        return 'le groupe est parmi';
    }

    public function negatedLabel(): ?string
    {
        return "le groupe n'est pas parmi";
    }

    public function params(): array
    {
        return ['groupes' => RuleParam::entities('groupe')];
    }

    public function describe(array $display, bool $not): string
    {
        return ($not ? "le groupe n'est pas parmi " : 'le groupe est parmi ') . $display['groupes'];
    }

    /** @param BaseGroupe $subject */
    public function matches(object $subject, array $params): bool
    {
        return Ids::contains($params['groupes'] ?? [], $subject);
    }
}
