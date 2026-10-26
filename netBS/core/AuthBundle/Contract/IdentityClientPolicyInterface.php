<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Contract;

interface IdentityClientPolicyInterface
{
    /**
     * Whether this user is allowed to obtain tokens for this client.
     */
    public function canAccess(IdentityDTO $identity, string $clientId): bool;
}
