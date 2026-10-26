<?php

declare(strict_types=1);

namespace App\Identity\UserModule;

use App\Entity\BSUser;

/**
 * How a BSUser presents itself to other systems, shared by the OIDC identity
 * ({@see IdentityUserResolver}) and the LDAP directory
 * ({@see DirectoryUserProvider}) so the two never drift apart.
 */
final class BSUserIdentity
{
    public static function displayName(BSUser $user): string
    {
        $membre = $user->getMembre();
        $name = $membre ? trim($membre->getFullName()) : '';

        return $name !== '' ? $name : $user->getUsername();
    }

    /** Personal email, else the BS one; empty strings count as missing. */
    public static function email(BSUser $user): ?string
    {
        return $user->getEmail() ?: $user->getEmailBS() ?: null;
    }
}
