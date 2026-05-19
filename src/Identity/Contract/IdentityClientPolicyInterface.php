<?php

declare(strict_types=1);

namespace App\Identity\Contract;

interface IdentityClientPolicyInterface
{
    /**
     * Whether this user is allowed to obtain tokens for this client.
     */
    public function canAccess(IdentityDTO $identity, string $clientId): bool;

    /**
     * Per-client custom claims (e.g. wiki_admin, nextcloud_quota).
     *
     * @return array<string, mixed>
     */
    public function additionalClaimsFor(IdentityDTO $identity, string $clientId): array;
}
