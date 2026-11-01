<?php

namespace NetBS\CoreBundle\Rule;

/** One block with its parameters, optionally negated. */
final class RuleCondition
{
    public function __construct(
        public readonly string $block,
        public readonly array $params,
        public readonly bool $not = false,
    ) {
    }
}
