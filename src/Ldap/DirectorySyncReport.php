<?php

declare(strict_types=1);

namespace App\Ldap;

final class DirectorySyncReport
{
    /**
     * @param string[] $changes what was (or, on a dry run, would be) changed
     * @param string[] $errors  failed operations and conflicts that were skipped
     */
    public function __construct(
        public readonly array $changes,
        public readonly array $errors,
    ) {
    }
}
