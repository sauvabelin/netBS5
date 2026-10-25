<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Message;

/** Asks the worker to make lldap match netBS, see {@see \NetBS\AuthBundle\Directory\DirectorySyncRunner}. */
final class SyncDirectory
{
    public function __construct(
        /** When the sync was asked for; skipped if a sync started since. Null always runs. */
        public readonly ?\DateTimeImmutable $requestedAt = null,
    ) {
    }
}
