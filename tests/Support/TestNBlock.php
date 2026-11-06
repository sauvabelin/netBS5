<?php

namespace App\Tests\Support;

use NetBS\CoreBundle\Rule\Operator;
use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;

class TestNBlock implements RuleBlock
{
    public function id(): string
    {
        return 'test.n';
    }

    public function contexts(): array
    {
        return ['test'];
    }

    public function label(): string
    {
        return 'n';
    }

    public function negatedLabel(): ?string
    {
        return null;
    }

    public function params(): array
    {
        return ['op' => RuleParam::operator(), 'value' => RuleParam::int()];
    }

    public function describe(array $display, bool $not): string
    {
        return ($not ? "n n'est pas " : 'n ') . $display['op'] . ' ' . $display['value'];
    }

    public function matches(object $subject, array $params): bool
    {
        return Operator::compare($subject['n'], $params['op'], (int) $params['value']);
    }
}
