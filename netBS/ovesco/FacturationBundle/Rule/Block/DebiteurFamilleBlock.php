<?php

namespace Ovesco\FacturationBundle\Rule\Block;

use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\FichierBundle\Mapping\BaseFamille;
use Ovesco\FacturationBundle\Entity\Facture;

class DebiteurFamilleBlock implements RuleBlock
{
    public function id(): string
    {
        return 'facture.debiteur_famille';
    }

    public function contexts(): array
    {
        return ['facture'];
    }

    public function label(): string
    {
        return 'le débiteur est une famille';
    }

    public function negatedLabel(): ?string
    {
        return "le débiteur n'est pas une famille";
    }

    public function params(): array
    {
        return [];
    }

    public function describe(array $display, bool $not): string
    {
        return $not ? "le débiteur n'est pas une famille" : 'le débiteur est une famille';
    }

    /** @param Facture $subject */
    public function matches(object $subject, array $params): bool
    {
        return $subject->getDebiteur() instanceof BaseFamille;
    }
}
