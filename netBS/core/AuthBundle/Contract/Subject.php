<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Contract;

/**
 * The one rule turning a netBS username into the identifier other systems
 * see: the OIDC subject (Hydra login, logout, session revocation, claims) and
 * the LDAP uid. lldap stores user ids lowercase whatever it is sent, so the
 * subject is lowercased too and both always agree.
 */
final class Subject
{
    public static function fromUsername(string $username): string
    {
        return mb_strtolower($username);
    }
}
