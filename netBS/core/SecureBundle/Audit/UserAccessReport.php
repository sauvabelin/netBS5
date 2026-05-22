<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Audit;

use NetBS\SecureBundle\Mapping\BaseUser;

final readonly class UserAccessReport
{
    /**
     * @param AccessGrant[] $grants
     * @param int $sensitiveRoleCount Distinct sensitive role names this user effectively holds.
     * @param int $scopeCount         Distinct scopes (Groupes) this user has access to.
     */
    public function __construct(
        public BaseUser $user,
        public array $grants,
        public int $sensitiveRoleCount = 0,
        public int $scopeCount = 0,
    ) {}
}
