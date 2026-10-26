<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * A user's username and login username must not be used by any other user,
 * in either column. See {@see \App\Repository\BSUserRepository}.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class UniqueLoginHandle extends Constraint
{
    public string $message = 'Ce nom de connexion est déjà pris.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
