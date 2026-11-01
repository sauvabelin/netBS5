<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

/**
 * No group would be published: there is no publication rule nor computed
 * group, or they give no group (and freeze none). Syncing anyway would delete
 * every netBS-managed group in lldap, so the sync refuses to run.
 */
final class DirectoryNotConfiguredException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct("Aucun groupe n'est publié : ajoutez une règle de publication ou un groupe calculé sur cette page (Administration › Authentication › LDAP). La synchronisation est suspendue, elle supprimerait tous les groupes de l'annuaire.");
    }
}
