<?php

namespace App\Tests\Support;

use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;

class TestGroupeBlock implements RuleBlock
{
    public function id(): string
    {
        return 'test.groupes';
    }

    public function contexts(): array
    {
        return ['test'];
    }

    public function label(): string
    {
        return 'est dans';
    }

    public function negatedLabel(): ?string
    {
        return "n'est pas dans";
    }

    public function params(): array
    {
        return ['groupes' => RuleParam::entities('groupe')];
    }

    public function describe(array $display, bool $not): string
    {
        return ($not ? "n'est pas dans " : 'est dans ') . $display['groupes'];
    }

    public function matches(object $subject, array $params): bool
    {
        return in_array($subject['groupe'] ?? null, $params['groupes'] ?? [], false);
    }
}
