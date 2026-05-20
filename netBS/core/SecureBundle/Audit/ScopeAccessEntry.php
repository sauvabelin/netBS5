<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Audit;

use NetBS\SecureBundle\Mapping\BaseUser;

final readonly class ScopeAccessEntry
{
    /** @param AccessGrant[] $grants */
    public function __construct(public BaseUser $user, public array $grants) {}
}
