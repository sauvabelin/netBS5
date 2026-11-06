<?php

namespace NetBS\CoreBundle\Rule;

/** One condition type usable in rules (tag netbs.rule_block). */
interface RuleBlock
{
    public function id(): string;

    /** @return list<string> context ids */
    public function contexts(): array;

    /** The condition as the editor offers it, naming its subject: « le membre a la fonction ». */
    public function label(): string;

    /** The negated form (« le membre n'a pas la fonction »), or null when the operator already covers it. */
    public function negatedLabel(): ?string;

    /** @return array<string,RuleParam> */
    public function params(): array;

    /** @param array<string,string> $display param => display text (entity params: names joined with ", ") */
    public function describe(array $display, bool $not): string;

    public function matches(object $subject, array $params): bool;
}
