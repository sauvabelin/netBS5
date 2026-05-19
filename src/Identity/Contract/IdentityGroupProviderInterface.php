<?php

declare(strict_types=1);

namespace App\Identity\Contract;

interface IdentityGroupProviderInterface
{
    /**
     * @return string[] Group names this user belongs to.
     */
    public function groupsFor(IdentityDTO $identity): array;
}
