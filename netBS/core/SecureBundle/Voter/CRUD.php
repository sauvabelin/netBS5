<?php

namespace NetBS\SecureBundle\Voter;

class CRUD {

    const   CREATE  = 'create';
    const   READ    = 'read';
    const   UPDATE  = 'update';
    const   DELETE  = 'delete';

    public static function toArray() {

        return [self::CREATE, self::READ, self::UPDATE, self::DELETE];
    }
}