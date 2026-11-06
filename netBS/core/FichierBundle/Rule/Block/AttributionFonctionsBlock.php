<?php

namespace NetBS\FichierBundle\Rule\Block;

use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;
use NetBS\FichierBundle\Rule\AttributionInSousGroupe;
use NetBS\FichierBundle\Rule\Ids;

class AttributionFonctionsBlock implements RuleBlock
{
    public function id(): string
    {
        return 'attribution.fonctions';
    }

    public function contexts(): array
    {
        return ['attribution_sous_groupe'];
    }

    public function label(): string
    {
        return 'sa fonction dans le sous-groupe est';
    }

    public function negatedLabel(): ?string
    {
        return "sa fonction dans le sous-groupe n'est pas";
    }

    public function params(): array
    {
        return ['fonctions' => RuleParam::entities('fonction')];
    }

    public function describe(array $display, bool $not): string
    {
        return ($not ? "sa fonction dans le sous-groupe n'est pas " : "sa fonction dans le sous-groupe est ") . $display['fonctions'];
    }

    /** @param AttributionInSousGroupe $subject */
    public function matches(object $subject, array $params): bool
    {
        return Ids::contains($params['fonctions'] ?? [], $subject->attribution->getFonction());
    }
}
