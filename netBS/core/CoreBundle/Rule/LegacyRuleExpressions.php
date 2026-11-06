<?php

namespace NetBS\CoreBundle\Rule;

/**
 * Expression-language strings still in use from before the rules existed (tag
 * netbs.legacy_rule_expressions), linted by netbs:rules:check.
 */
interface LegacyRuleExpressions
{
    /** @return list<array{label: string, context: string, expression: string}> */
    public function expressions(): array;
}
