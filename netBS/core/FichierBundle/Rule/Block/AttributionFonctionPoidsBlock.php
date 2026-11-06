<?php

namespace NetBS\FichierBundle\Rule\Block;

use NetBS\CoreBundle\Rule\Operator;
use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;
use NetBS\FichierBundle\Rule\AttributionInSousGroupe;

class AttributionFonctionPoidsBlock implements RuleBlock
{
    public function id(): string
    {
        return 'attribution.fonction_poids';
    }

    public function contexts(): array
    {
        return ['attribution_sous_groupe'];
    }

    public function label(): string
    {
        return 'sa fonction a un poids';
    }

    public function negatedLabel(): ?string
    {
        return null;
    }

    public function params(): array
    {
        return ['op' => RuleParam::operator(), 'poids' => RuleParam::int(0, 1000)];
    }

    public function describe(array $display, bool $not): string
    {
        return ($not ? "sa fonction n'a pas un poids " : "sa fonction a un poids ") . $display['op'] . ' ' . $display['poids'];
    }

    /** @param AttributionInSousGroupe $subject */
    public function matches(object $subject, array $params): bool
    {
        $fonction = $subject->attribution->getFonction();

        return $fonction !== null && Operator::compare((int) $fonction->getPoids(), $params['op'], (int) $params['poids']);
    }
}
