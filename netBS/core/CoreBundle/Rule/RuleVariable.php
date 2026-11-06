<?php

namespace NetBS\CoreBundle\Rule;

/** One variable of a rule context, for expert expressions and the glossary. */
final class RuleVariable
{
    /** @param list<string> $types class names (their getters are listed in the glossary) or plain words for scalars */
    public function __construct(
        public readonly string $description,
        public readonly array $types,
    ) {
    }
}
