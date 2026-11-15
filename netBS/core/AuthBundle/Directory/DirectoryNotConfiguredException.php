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
        parent::__construct($message ?? "Aucun groupe n'est partagé pour l'instant. Ajoutez une règle de partage ou un groupe automatique. D'ici là, la synchronisation est en pause pour ne pas supprimer les groupes déjà partagés.");
    }

    /** @param string $owner e.g. « Règle « Unités » » */
    public static function brokenRule(string $owner, string $problem): self
    {
        return new self(sprintf('%s : %s. La synchronisation reste en pause tant que ce n\'est pas corrigé.', $owner, rtrim($problem, '.')));
    }
}
