<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

/**
 * No groupe would become a directory group: no groupe type is set to "Crée un
 * groupe", or the ticked types have no groupes. Syncing anyway would delete
 * every netBS-managed group in lldap, so the sync refuses to run.
 */
final class DirectoryNotConfiguredException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('No groupe would become a directory group (check "Crée un groupe" on the groupe types); refusing to sync (it would delete every directory group).');
    }
}
