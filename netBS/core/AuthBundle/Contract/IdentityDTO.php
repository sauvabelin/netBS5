<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Contract;

/**
 * Immutable identity DTO crossing the User → Identity boundary.
 * No open-ended custom-claims bag — new claims are added as explicit fields.
 * No groups either: OIDC only does login and access decisions, group
 * membership reaches the apps through the LDAP directory.
 */
final readonly class IdentityDTO
{
    public function __construct(
        public string $sub,
        public string $preferredUsername,
        public ?string $email,
        public string $displayName,
        public bool $isDisabled,
    ) {
    }
}
