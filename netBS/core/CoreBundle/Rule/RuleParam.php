<?php

namespace NetBS\CoreBundle\Rule;

/** Declares the type of one block parameter. */
final class RuleParam
{
    const OPERATOR = 'operator';
    const INT      = 'int';
    const CHOICE   = 'choice';
    const ENTITIES = 'entities';

    private function __construct(
        public readonly string $kind,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly array $choices = [],
        public readonly ?string $target = null,
    ) {
    }

    public static function operator(): self
    {
        return new self(self::OPERATOR, choices: Operator::CHOICES);
    }

    public static function int(?int $min = null, ?int $max = null): self
    {
        return new self(self::INT, $min, $max);
    }

    /** @param array<string,string> $choices value => label */
    public static function choice(array $choices): self
    {
        return new self(self::CHOICE, choices: $choices);
    }

    /** @param string $target groupe|fonction|groupe_type */
    public static function entities(string $target): self
    {
        return new self(self::ENTITIES, target: $target);
    }
}
