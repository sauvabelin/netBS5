<?php

namespace NetBS\FichierBundle\Rule\Block;

use NetBS\CoreBundle\Rule\Operator;
use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;
use NetBS\FichierBundle\Rule\AttributionInSousGroupe;

class AttributionProfondeurBlock implements RuleBlock
{
    public function id(): string
    {
        return 'attribution.profondeur';
    }

    public function contexts(): array
    {
        return ['attribution_sous_groupe'];
    }

    public function label(): string
    {
        return 'le sous-groupe est à une profondeur';
    }

    public function negatedLabel(): ?string
    {
        return null;
    }

    public function params(): array
    {
        return ['op' => RuleParam::operator(), 'profondeur' => RuleParam::int(1, 20)];
    }

    public function describe(array $display, bool $not): string
    {
        return ($not ? "le sous-groupe n'est pas à une profondeur " : "le sous-groupe est à une profondeur ") . $display['op'] . ' ' . $display['profondeur'];
    }

    /** @param AttributionInSousGroupe $subject */
    public function matches(object $subject, array $params): bool
    {
        return Operator::compare($subject->profondeur, $params['op'], (int) $params['profondeur']);
    }
}
