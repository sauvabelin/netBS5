<?php

namespace NetBS\CoreBundle\Rule;

/** Conditions and nested groups combined with « et » (and) or « ou » (or). */
final class RuleGroup
{
    const OPS = ['and', 'or'];

    /** @param list<RuleGroup|RuleCondition> $items */
    public function __construct(
        public readonly string $op,
        public readonly array $items,
    ) {
        if (!in_array($op, self::OPS, true)) {
            throw new InvalidRuleException(sprintf('Unknown group operator "%s".', $op));
        }
    }
}
