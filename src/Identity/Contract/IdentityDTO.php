<?php

declare(strict_types=1);

namespace App\Identity\Contract;

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
        public bool $emailVerified,
        public string $displayName,
        public array $groups,
        public bool $isDisabled,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
