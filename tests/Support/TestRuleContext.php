<?php

namespace App\Tests\Support;

use NetBS\CoreBundle\Rule\RuleContext;
use NetBS\CoreBundle\Rule\RuleVariable;

/** Rule context for engine tests: the subject is an \ArrayObject with keys n (int) and flag (bool). */
class TestRuleContext implements RuleContext
{
    public function id(): string
    {
        return 'test';
    }

    public function label(): string
    {
        return 'Test';
    }

    public function variables(): array
    {
        return ['n' => new RuleVariable('un nombre', ['nombre'])];
    }

    public function example(): string
    {
        return 'n > 3';
    }

    public function expressionValues(object $subject): array
    {
        return ['n' => $subject['n']];
    }
}
