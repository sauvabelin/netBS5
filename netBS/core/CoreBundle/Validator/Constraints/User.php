<?php

namespace NetBS\CoreBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class User extends Constraint
{
    public $rule    = null;
    public $rules   = [];
    public $key     = "user";

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}