<?php

namespace NetBS\FichierBundle\Rule;

use NetBS\CoreBundle\Rule\RuleContext;
use NetBS\CoreBundle\Rule\RuleVariable;
use NetBS\FichierBundle\Mapping\BaseGroupe;

/** A groupe, e.g. when choosing which groupes the LDAP directory publishes. */
class GroupeContext implements RuleContext
{
    public function id(): string
    {
        return 'groupe';
    }

    public function label(): string
    {
        return 'Groupe';
    }

    public function variables(): array
    {
        return [
            'groupe' => new RuleVariable('Le groupe ; getParent() donne son groupe parent', [BaseGroupe::class]),
        ];
    }

    public function example(): string
    {
        return 'groupe.getGroupeType().getNom() == "Unité"';
    }

    public function expressionValues(object $subject): array
    {
        return ['groupe' => $subject];
    }
}
