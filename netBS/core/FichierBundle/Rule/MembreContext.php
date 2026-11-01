<?php

namespace NetBS\FichierBundle\Rule;

use NetBS\CoreBundle\Rule\RuleContext;
use NetBS\CoreBundle\Rule\RuleVariable;
use NetBS\FichierBundle\Mapping\BaseMembre;

class MembreContext implements RuleContext
{
    public function id(): string
    {
        return 'membre';
    }

    public function label(): string
    {
        return 'Membre';
    }

    public function variables(): array
    {
        return [
            'membre' => new RuleVariable('Le membre ; getActivesAttributions() donne ses attributions en cours', [BaseMembre::class]),
        ];
    }

    public function example(): string
    {
        return 'membre.getStatut() == "inscrit" and membre.getFamille() != null';
    }

    public function expressionValues(object $subject): array
    {
        return ['membre' => $subject];
    }
}
