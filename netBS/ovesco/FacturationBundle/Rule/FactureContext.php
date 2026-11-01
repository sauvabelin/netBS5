<?php

namespace Ovesco\FacturationBundle\Rule;

use NetBS\CoreBundle\Rule\RuleContext;
use NetBS\CoreBundle\Rule\RuleVariable;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Mapping\BaseFamille;
use Ovesco\FacturationBundle\Entity\Facture;

class FactureContext implements RuleContext
{
    public function id(): string
    {
        return 'facture';
    }

    public function label(): string
    {
        return 'Facture';
    }

    public function variables(): array
    {
        return [
            'facture'   => new RuleVariable('La facture ; getCreances() et getRappels() sont des listes (.count() donne leur nombre)', [Facture::class]),
            'debiteur'  => new RuleVariable('Le débiteur, un membre ou une famille', [BaseMembre::class, BaseFamille::class]),
            'isFamille' => new RuleVariable('Vrai si le débiteur est une famille', ['booléen']),
        ];
    }

    public function example(): string
    {
        return 'facture.getRappels().count() == 1 and not isFamille';
    }

    /** @param Facture $subject */
    public function expressionValues(object $subject): array
    {
        return [
            'facture'   => $subject,
            'debiteur'  => $subject->getDebiteur(),
            'isFamille' => $subject->getDebiteur() instanceof BaseFamille,
        ];
    }
}
