<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Contract;

interface IdentityUserResolverInterface
{
    /**
     * Resolve a user by their immutable subject identifier (= username).
     * Returns null if the user does not exist or is disabled.
     */
    public function resolveBySub(string $sub): ?IdentityDTO;
}
