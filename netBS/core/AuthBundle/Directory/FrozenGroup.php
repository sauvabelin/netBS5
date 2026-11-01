<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

/**
 * A group whose rule is broken: the sync leaves its lldap copy exactly as it
 * is (no member change, no rename, no delete, no create) until the rule is
 * fixed. Other groups keep syncing.
 */
final class FrozenGroup
{
    public function __construct(
        /** As {@see DirectoryGroup::key()}. */
        public readonly string $key,
        /** The name the group would have. */
        public readonly string $name,
        /** « {rule owner} : {message} », for logs and the LDAP page. */
        public readonly string $reason,
    ) {
    }
}
