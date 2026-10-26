<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Contract;

use NetBS\AuthBundle\Directory\DirectoryUser;

interface DirectoryUserProviderInterface
{
    /**
     * Every account that belongs in the LDAP directory, with the membre it is
     * linked to (so groupe memberships can be mapped to uids).
     *
     * @return array<string, DirectoryUser> keyed by uid
     */
    public function directoryUsers(): array;

    /**
     * Membre id => uid, for the accounts {@see directoryUsers()} lists that
     * have a membre. Cheaper than directoryUsers(), for resolving one group.
     *
     * @return array<int, string>
     */
    public function uidsByMembre(): array;
}
