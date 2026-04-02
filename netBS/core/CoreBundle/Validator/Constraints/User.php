<?php

namespace NetBS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
class User extends Constraint
{
    public $rule    = null;
    public $rules   = [];
    public $key     = "user";

    public function __construct(
        ?string $rule = null,
        array $rules = [],
        string $key = "user",
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options, $groups, $payload);

        $this->rule = $rule ?? $this->rule;
        $this->rules = $rules ?: $this->rules;
        $this->key = $key;
    }

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
