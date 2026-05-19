<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Contract;

interface IdentityGroupProviderInterface
{
    /**
     * @return string[] Group names this user belongs to.
     */
    public function groupsFor(IdentityDTO $identity): array;
}
