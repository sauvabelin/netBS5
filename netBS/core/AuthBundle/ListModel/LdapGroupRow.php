<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\ListModel;

/** One line of the LDAP page's groups list ({@see LdapGroupListModel}). */
final class LdapGroupRow
{
    public function __construct(
        /** Groupe id (as a string), "c" + computed group id, or role name; also the members endpoint key. */
        public readonly string $key,
        public readonly string $name,
        /** Set for groupe groups, to link to the groupe page. */
        public readonly ?int $groupeId,
        public readonly string $origin,
    ) {
    }

    /** Row id used by the list renderer. */
    public function getId(): string
    {
        return $this->key;
    }
}
