<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

final class DirectoryGroup
{
    /**
     * @param string[] $members uids
     */
    public function __construct(
        /** Id of the netBS groupe; stored on the lldap group so renames don't create duplicates. */
        public readonly int $netbsId,
        public readonly string $name,
        public readonly array $members,
    ) {
    }
}
