<?php

namespace NetBS\FichierBundle\Rule\Block;

use NetBS\CoreBundle\Rule\Operator;
use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Rule\Ids;

class MembreFonctionPoidsBlock implements RuleBlock
{
    public function id(): string
    {
        return 'membre.fonction_poids';
    }

    public function contexts(): array
    {
        return ['membre'];
    }

    public function label(): string
    {
        return 'le membre a une fonction de poids';
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
        return ($not ? "le membre n'a pas de fonction de poids " : 'le membre a une fonction de poids ') . $display['op'] . ' ' . $display['poids'];
    }

    /** @param BaseMembre $subject */
    public function matches(object $subject, array $params): bool
    {
        foreach ($subject->getActivesAttributions() as $attribution) {
            if ($attribution->getFonction() && Operator::compare((int) $attribution->getFonction()->getPoids(), $params['op'], (int) $params['poids'])) {
                return true;
            }
        }

        return false;
    }
}
