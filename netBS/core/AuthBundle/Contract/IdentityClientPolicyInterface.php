<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Contract;

interface IdentityClientPolicyInterface
{
    /**
     * Whether this user is allowed to obtain tokens for this client.
     */
    public function canAccess(IdentityDTO $identity, string $clientId): bool;

    /**
     * User-derived claims beyond the standard OIDC set (e.g. wiki_admin,
     * nextcloud_quota).
     *
     * Implementations MUST NOT branch on `$clientId` to decide which claims
     * to include or omit: per-client filtering is the sole responsibility of
     * `ClaimsAssembler`, which consults each client's
     * `metadata.allowed_claims` allow-list in Hydra. Returning a claim here
     * just makes it eligible; the assembler decides which clients see it.
     *
     * `$clientId` is passed in for the narrow case where a claim's *value*
     * (not its presence) needs per-client shaping — keep that rare.
     *
     * @return array<string, mixed>
     */
    public function additionalClaimsFor(IdentityDTO $identity, string $clientId): array;
}
