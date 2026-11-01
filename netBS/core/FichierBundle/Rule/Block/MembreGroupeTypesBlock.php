<?php

namespace NetBS\FichierBundle\Rule\Block;

use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Rule\Ids;

class MembreGroupeTypesBlock implements RuleBlock
{
    public function id(): string
    {
        return 'membre.groupe_types';
    }

    public function contexts(): array
    {
        return ['membre'];
    }

    public function label(): string
    {
        return 'le membre a une attribution dans un groupe de type';
    }

    public function negatedLabel(): ?string
    {
        return "le membre n'a pas d'attribution dans un groupe de type";
    }

    public function params(): array
    {
        return ['types' => RuleParam::entities('groupe_type')];
    }

    public function describe(array $display, bool $not): string
    {
        return ($not ? "le membre n'a pas d'attribution dans un groupe de type " : 'le membre a une attribution dans un groupe de type ') . $display['types'];
    }

    /** @param BaseMembre $subject */
    public function matches(object $subject, array $params): bool
    {
        foreach ($subject->getActivesAttributions() as $attribution) {
            if (Ids::contains($params['types'] ?? [], $attribution->getGroupe()?->getGroupeType())) {
                return true;
            }
        }

        return false;
    }
}
