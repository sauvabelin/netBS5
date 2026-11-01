<?php

namespace NetBS\FichierBundle\Rule\Block;

use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Rule\Ids;

class MembreFonctionsBlock implements RuleBlock
{
    public function id(): string
    {
        return 'membre.fonctions';
    }

    public function contexts(): array
    {
        return ['membre'];
    }

    public function label(): string
    {
        return 'le membre a la fonction';
    }

    public function negatedLabel(): ?string
    {
        return "le membre n'a pas la fonction";
    }

    public function params(): array
    {
        return ['fonctions' => RuleParam::entities('fonction')];
    }

    public function describe(array $display, bool $not): string
    {
        return ($not ? "le membre n'a pas la fonction " : 'le membre a la fonction ') . $display['fonctions'];
    }

    /** @param BaseMembre $subject */
    public function matches(object $subject, array $params): bool
    {
        foreach ($subject->getActivesAttributions() as $attribution) {
            if (Ids::contains($params['fonctions'] ?? [], $attribution->getFonction())) {
                return true;
            }
        }

        return false;
    }
}
