<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

/** What the directory should contain, as computed by {@see DirectoryProjection}. */
final class DirectorySnapshot
{
    /**
     * @param array<string, DirectoryUser> $users keyed by uid
     * @param array<int, DirectoryGroup> $groups groupe groups, keyed by netBS groupe id
     * @param array<string, DirectoryGroup> $roleGroups keyed by role
     */
    public function __construct(
        public readonly array $users,
        public readonly array $groups,
        public readonly array $roleGroups,
    ) {
    }
}
