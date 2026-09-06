<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Contract;

interface IdentityUserResolverInterface
{
    /**
     * Resolve a user by their immutable subject identifier (= username).
     *
     * Returns null only when the subject does not exist locally. Disabled
     * accounts ARE returned as an IdentityDTO with `isDisabled=true`; it is
     * up to each caller to decide what to do with them (e.g. the consent
     * flow refuses, the refresh-token hook revokes the session). Hiding
     * disabled users behind null would force callers to re-query just to
     * tell "unknown" from "disabled" apart.
     */
    public function resolveBySub(string $sub): ?IdentityDTO;
}
