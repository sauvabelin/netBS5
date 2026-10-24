<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\ListModel;

/** One pending change of the LDAP page ({@see LdapChangeListModel}). */
final class LdapChangeRow
{
    public function __construct(
        private readonly int $id,
        public readonly string $change,
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }
}
