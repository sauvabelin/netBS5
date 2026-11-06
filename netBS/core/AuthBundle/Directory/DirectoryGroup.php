<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

/**
 * A group netBS owns in lldap: a groupe group ($netbsId set), a computed
 * group ($computedId set) or a role group ($role set, see
 * {@see DirectoryProjection::ROLE_GROUPS}).
 */
final class DirectoryGroup
{
    /**
     * @param string[] $members uids
     */
    public function __construct(
        /** Id of the netBS groupe; stored on the lldap group so renames don't create duplicates. */
        public readonly ?int $netbsId,
        /** lldap group name (cn, part of the DN): no characters that need DN escaping. */
        public readonly string $name,
        public readonly array $members,
        /** Role the group publishes; stored on the lldap group like $netbsId. */
        public readonly ?string $role = null,
        /** Id of the computed group; stored on the lldap group like $netbsId. */
        public readonly ?int $computedId = null,
        /** Where the group comes from, for display only. */
        public readonly ?string $origin = null,
    ) {
    }

    /** Identifies a managed group across runs, whatever its name. */
    public static function key(?int $netbsId, ?string $role, ?int $computedId = null): string
    {
        return match (true) {
            $role !== null       => "role:$role",
            $computedId !== null => "computed:$computedId",
            default              => "id:$netbsId",
        };
    }
}
