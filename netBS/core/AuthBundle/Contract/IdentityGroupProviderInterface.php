<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Contract;

interface IdentityGroupProviderInterface
{
    /**
     * Resolve the group names a user belongs to.
     *
     * The caller passes the application-specific user entity it has already
     * loaded (typed `object` here because the contract lives in AuthBundle
     * and must not depend on `App\Entity\BSUser`). Implementations narrow the
     * type to their concrete entity. This sidesteps a second DB fetch that
     * would be needed if we passed only an `IdentityDTO` or a `sub` string.
     *
     * @return string[] Group names this user belongs to.
     */
    public function groupsFor(object $user): array;
}
