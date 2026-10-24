<?php

declare(strict_types=1);

namespace App\Ldap;

final class DirectoryUser
{
    public function __construct(
        /** lldap user id == netBS username (immutable, also the OIDC subject). Lowercase: lldap ids are case-insensitive. */
        public readonly string $uid,
        /** lldap requires a unique email on every user. */
        public readonly string $email,
        public readonly string $displayName,
        public readonly string $firstName,
        public readonly string $lastName,
    ) {
    }

    /** Whether the lldap copy ($current, a row from {@see LldapClientInterface::listUsers()}) needs updating. */
    public function differsFrom(array $current): bool
    {
        return $this->email !== $current['email']
            || $this->displayName !== $current['displayName']
            || $this->firstName !== $current['firstName']
            || $this->lastName !== $current['lastName'];
    }
}
