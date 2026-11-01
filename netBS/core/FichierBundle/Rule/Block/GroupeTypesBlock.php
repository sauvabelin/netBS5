<?php

namespace NetBS\FichierBundle\Rule\Block;

use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Rule\Ids;

class GroupeTypesBlock implements RuleBlock
{
    public function id(): string
    {
        return 'groupe.types';
    }

    public function contexts(): array
    {
        return ['groupe'];
    }

    public function label(): string
    {
        return 'le groupe est de type';
    }

    public function negatedLabel(): ?string
    {
        return "le groupe n'est pas de type";
    }

    public function params(): array
    {
        return ['types' => RuleParam::entities('groupe_type')];
    }

    public function describe(array $display, bool $not): string
    {
        return ($not ? "le groupe n'est pas de type " : 'le groupe est de type ') . $display['types'];
    }

    /** @param BaseGroupe $subject */
    public function matches(object $subject, array $params): bool
    {
        return Ids::contains($params['types'] ?? [], $subject->getGroupeType());
    }
}
