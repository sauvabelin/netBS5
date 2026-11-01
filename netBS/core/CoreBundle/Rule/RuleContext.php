<?php

namespace NetBS\CoreBundle\Rule;

/** What a rule is about (tag netbs.rule_context): its variables for expert mode and the glossary. */
interface RuleContext
{
    public function id(): string;

    public function label(): string;

    /** @return array<string,RuleVariable> variable name => variable */
    public function variables(): array;

    /** A short valid expert expression, shown in the glossary. */
    public function example(): string;

    /** @return array<string,mixed> */
    public function expressionValues(object $subject): array;
}
