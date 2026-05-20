<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Audit;

/**
 * One cell of the sensitive-role matrix: the grants by which a user holds a role,
 * plus whether at least one path is explicit (vs. inherited via an ancestor role).
 */
final readonly class SensitiveRoleCell
{
    /** @param AccessGrant[] $grants */
    public function __construct(
        public array $grants,
        public bool $explicit,
    ) {}
}
