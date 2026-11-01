<?php

namespace NetBS\FichierBundle\Rule\Block;

use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;
use NetBS\FichierBundle\Rule\AttributionInSousGroupe;
use NetBS\FichierBundle\Rule\Ids;

class AttributionGroupeTypesBlock implements RuleBlock
{
    public function id(): string
    {
        return 'attribution.groupe_types';
    }

    public function contexts(): array
    {
        return ['attribution_sous_groupe'];
    }

    public function label(): string
    {
        return 'le sous-groupe est de type';
    }

    public function negatedLabel(): ?string
    {
        return "le sous-groupe n'est pas de type";
    }

    public function params(): array
    {
        return ['types' => RuleParam::entities('groupe_type')];
    }

    public function describe(array $display, bool $not): string
    {
        return ($not ? "le sous-groupe n'est pas de type " : "le sous-groupe est de type ") . $display['types'];
    }

    /** @param AttributionInSousGroupe $subject */
    public function matches(object $subject, array $params): bool
    {
        return Ids::contains($params['types'] ?? [], $subject->attribution->getGroupe()?->getGroupeType());
    }
}
