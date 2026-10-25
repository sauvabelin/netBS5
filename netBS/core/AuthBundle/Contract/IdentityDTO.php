<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Contract;

/**
 * Immutable identity DTO crossing the User → Identity boundary.
 * No open-ended custom-claims bag — new claims are added as explicit fields.
 * $groups only carries the directory role groups the user holds
 * ({@see \NetBS\AuthBundle\Directory\DirectoryProjection::ROLE_GROUPS}), so apps
 * without LDAP (the wiki) can map admin rights. Regular groupe membership still
 * reaches the apps through the LDAP directory only.
 */
final readonly class IdentityDTO
{
    public function __construct(
        public string $sub,
        public string $preferredUsername,
        public ?string $email,
        public string $displayName,
        public bool $isDisabled,
        /** @var list<string> role group names, e.g. ['wiki-admins'] */
        public array $groups = [],
    ) {
    }
}
