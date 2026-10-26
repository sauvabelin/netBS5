<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;

/**
 * Applies the directory sync, one run at a time. Every trigger (worker
 * message, schedule, console) goes through here.
 */
final class DirectorySyncRunner
{
    /** Start time of the last sync that reached lldap. */
    private const LAST_SYNC_KEY = 'netbs_directory_last_sync';
    /** Longer than any sync; the lock is released early when the run ends. */
    private const LOCK_TTL = 900;

    public function __construct(
        private readonly DirectoryProjection $projection,
        private readonly DirectorySync $sync,
        private readonly LockFactory $locks,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @param \DateTimeImmutable|null $requestedAt skip the run when a sync started after this;
     *                                             null always runs
     * @return DirectorySyncReport|null null when skipped
     * @throws DirectoryNotConfiguredException|LldapClientException
     */
    public function run(?\DateTimeImmutable $requestedAt = null): ?DirectorySyncReport
    {
        $lock = $this->locks->createLock('netbs_directory_sync', self::LOCK_TTL);
        $lock->acquire(true);
        try {
            $lastSync = $this->cache->getItem(self::LAST_SYNC_KEY);
            if ($requestedAt !== null && $lastSync->isHit() && $lastSync->get() >= $requestedAt) {
                return null;
            }

            $startedAt = new \DateTimeImmutable();
            $report = $this->sync->sync($this->projection->snapshot(), false);
            $this->cache->save($lastSync->set($startedAt));
            return $report;
        } finally {
            $lock->release();
        }
    }
}
