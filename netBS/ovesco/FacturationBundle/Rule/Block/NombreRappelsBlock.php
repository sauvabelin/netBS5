<?php

namespace Ovesco\FacturationBundle\Rule\Block;

use NetBS\CoreBundle\Rule\Operator;
use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;
use Ovesco\FacturationBundle\Entity\Facture;

class NombreRappelsBlock implements RuleBlock
{
    public function id(): string
    {
        return 'facture.nombre_rappels';
    }

    public function contexts(): array
    {
        return ['facture'];
    }

    public function label(): string
    {
        return 'le nombre de rappels est';
    }

    public function negatedLabel(): ?string
    {
        return null;
    }

    public function params(): array
    {
        return ['op' => RuleParam::operator(), 'nombre' => RuleParam::int(0)];
    }

    public function describe(array $display, bool $not): string
    {
        return ($not ? "le nombre de rappels n'est pas " : 'le nombre de rappels est ') . $display['op'] . ' ' . $display['nombre'];
    }

    /** @param Facture $subject */
    public function matches(object $subject, array $params): bool
    {
        return Operator::compare(count($subject->getRappels()), $params['op'], (int) $params['nombre']);
    }
}
