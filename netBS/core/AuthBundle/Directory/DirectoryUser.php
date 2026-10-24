<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

final class DirectoryUser
{
    public function __construct(
        /** lldap user id, the same string as the OIDC subject ({@see \NetBS\AuthBundle\Contract\Subject}). */
        public readonly string $uid,
        /** lldap requires a unique email on every user. */
        public readonly string $email,
        public readonly string $displayName,
        public readonly string $firstName,
        public readonly string $lastName,
        /** netBS membre linked to the account, if any; not stored in lldap. */
        public readonly ?int $membreId = null,
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
