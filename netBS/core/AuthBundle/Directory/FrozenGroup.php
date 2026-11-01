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
        /**
         * Frozen only because a broken selection rule might pick it: nothing is
         * held back unless lldap already has its group.
         */
        public readonly bool $uncertain = false,
    ) {
    }

    /**
     * The frozen groups worth listing (certain ones, and uncertain ones lldap
     * already has), and per reason how many uncertain ones were left out.
     *
     * @param array<string, self> $frozen keyed by {@see DirectoryGroup::key()}
     * @param array<string, true> $lldapKeys keys of the groups lldap has
     * @return array{list<self>, array<string, int>}
     */
    public static function split(array $frozen, array $lldapKeys): array
    {
        $shown = [];
        $pending = [];
        foreach ($frozen as $key => $group) {
            if (!$group->uncertain || isset($lldapKeys[$key])) {
                $shown[] = $group;
            } else {
                $pending[$group->reason] = ($pending[$group->reason] ?? 0) + 1;
            }
        }
        return [$shown, $pending];
    }
}
