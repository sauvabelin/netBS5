<?php

namespace NetBS\CoreBundle\Rule;

final class Operator
{
    const CHOICES = ['eq' => '=', 'ne' => '≠', 'lt' => '<', 'le' => '≤', 'gt' => '>', 'ge' => '≥'];

    public static function compare(int|float $a, string $op, int|float $b): bool
    {
        return match ($op) {
            'eq' => $a == $b,
            'ne' => $a != $b,
            'lt' => $a < $b,
            'le' => $a <= $b,
            'gt' => $a > $b,
            'ge' => $a >= $b,
            default => throw new InvalidRuleException(sprintf('Unknown operator "%s".', $op)),
        };
    }
}
