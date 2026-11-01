<?php

namespace Ovesco\FacturationBundle\Rule\Block;

use NetBS\CoreBundle\Rule\RuleBlock;
use Ovesco\FacturationBundle\Entity\Facture;

class ImprimeeBlock implements RuleBlock
{
    public function id(): string
    {
        return 'facture.imprimee';
    }

    public function contexts(): array
    {
        return ['facture'];
    }

    public function label(): string
    {
        return 'la facture a déjà été imprimée';
    }

    public function negatedLabel(): ?string
    {
        return "la facture n'a jamais été imprimée";
    }

    public function params(): array
    {
        return [];
    }

    public function describe(array $display, bool $not): string
    {
        return $not ? "la facture n'a jamais été imprimée" : 'la facture a déjà été imprimée';
    }

    /** @param Facture $subject */
    public function matches(object $subject, array $params): bool
    {
        return $subject->getLatestImpression() !== null;
    }
}
