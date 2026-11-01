<?php

namespace NetBS\FichierBundle\Rule;

use NetBS\CoreBundle\Rule\RuleContext;
use NetBS\CoreBundle\Rule\RuleVariable;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseFonction;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\BaseMembre;

class AttributionSousGroupeContext implements RuleContext
{
    public function id(): string
    {
        return 'attribution_sous_groupe';
    }

    public function label(): string
    {
        return 'Attribution dans un sous-groupe';
    }

    public function variables(): array
    {
        return [
            'attribution' => new RuleVariable("L'attribution en cours dans le sous-groupe", [BaseAttribution::class]),
            'membre'      => new RuleVariable('Le membre attribué', [BaseMembre::class]),
            'fonction'    => new RuleVariable("La fonction de l'attribution", [BaseFonction::class]),
            'groupe'      => new RuleVariable('Le sous-groupe', [BaseGroupe::class]),
            'profondeur'  => new RuleVariable('Distance entre le groupe du répertoire et le sous-groupe (1 = enfant direct)', ['nombre']),
        ];
    }

    public function example(): string
    {
        return 'fonction.getPoids() >= 50 and profondeur <= 2';
    }

    /** @param AttributionInSousGroupe $subject */
    public function expressionValues(object $subject): array
    {
        $attribution = $subject->attribution;

        return [
            'attribution' => $attribution,
            'membre'      => $attribution->getMembre(),
            'fonction'    => $attribution->getFonction(),
            'groupe'      => $attribution->getGroupe(),
            'profondeur'  => $subject->profondeur,
        ];
    }
}
