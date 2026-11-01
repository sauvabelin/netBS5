<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

/** A group as the LDAP page lists it, without its members ({@see DirectoryProjection::groupOutlines()}). */
final class GroupOutline
{
    public function __construct(
        /** Route key: groupe id, "c" + computed group id, or role. */
        public readonly string $key,
        public readonly string $name,
        /** Set for groupe groups. */
        public readonly ?int $groupeId,
        public readonly string $origin,
        /** Set when the group is frozen. */
        public readonly ?string $frozenReason = null,
        /** See {@see FrozenGroup::$uncertain}. */
        public readonly bool $uncertain = false,
    ) {
    }
}
