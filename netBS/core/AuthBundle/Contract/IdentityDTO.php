<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Contract;

/**
 * Immutable identity DTO crossing the User → Identity boundary.
 * No open-ended custom-claims bag — new claims are added as explicit fields.
 */
final readonly class IdentityDTO
{
    /**
     * @param string[] $groups
     */
    public function __construct(
        public string $sub,
        public string $preferredUsername,
        public ?string $email,
        public string $displayName,
        public array $groups,
        public bool $isDisabled,
    ) {
    }
}
