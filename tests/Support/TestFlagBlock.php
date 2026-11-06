<?php

namespace App\Tests\Support;

use NetBS\CoreBundle\Rule\Operator;
use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;

class TestFlagBlock implements RuleBlock
{
    public function id(): string
    {
        return 'test.flag';
    }

    public function contexts(): array
    {
        return ['test'];
    }

    public function label(): string
    {
        return 'est marqué';
    }

    public function negatedLabel(): ?string
    {
        return "n'est pas marqué";
    }

    public function params(): array
    {
        return [];
    }

    public function describe(array $display, bool $not): string
    {
        return $not ? "n'est pas marqué" : 'est marqué';
    }

    public function matches(object $subject, array $params): bool
    {
        return ($subject['flag'] ?? false) === true;
    }
}
