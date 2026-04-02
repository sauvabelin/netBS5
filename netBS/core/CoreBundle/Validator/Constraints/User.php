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

    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
