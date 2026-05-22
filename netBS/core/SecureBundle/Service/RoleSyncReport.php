<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Service;

/**
 * Summary of what a single {@see RoleTreeSyncer::syncAll} run did.
 *
 * Role names are tracked here, not Role entities, so the report stays
 * meaningful after the entity manager is cleared.
 */
final class RoleSyncReport
{
    /** @var string[] */
    public array $created = [];

    /** @var string[] */
    public array $updated = [];

    /** @var string[] */
    public array $unchanged = [];

    /** Number of duplicate role rows removed at the start of the sync. */
    public int $dedupedFrom = 0;
}
