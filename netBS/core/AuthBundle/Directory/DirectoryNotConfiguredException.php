<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

/**
 * The sync refuses to run: either no group would be published (syncing would delete every
 * netBS-managed group in lldap), or a rule is broken (invalid, deleted reference, failing at run
 * time), which could publish wrong groups. All or nothing: nothing is synced until it is fixed.
 */
final class DirectoryNotConfiguredException extends \RuntimeException
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? "Aucun groupe n'est publié : ajoutez une règle de publication ou un groupe calculé sur cette page (Administration › Authentication › LDAP). La synchronisation est suspendue, elle supprimerait tous les groupes de l'annuaire.");
    }

    /** @param string $owner e.g. « Règle « Unités » » */
    public static function brokenRule(string $owner, string $problem): self
    {
        return new self(sprintf('%s : %s. La synchronisation est suspendue jusqu\'à sa correction.', $owner, rtrim($problem, '.')));
    }
}
