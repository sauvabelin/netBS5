<?php

declare(strict_types=1);

namespace App\Service;

use NetBS\SecureBundle\Service\RoleTreeSourceInterface;

/**
 * App-specific role subtree (`ROLE_QM`, `ROLE_CB`, `ROLE_CHEF_CLAN`, …)
 * grafted under `ROLE_COMMANDANT` (which is itself created by
 * FichierBundle's source).
 *
 * Runs last (order 500) so both system and Fichier sources have already
 * created ROLE_COMMANDANT before this graft point is looked up.
 *
 * Note: the YAML re-declares ROLE_COMMANDANT at its top level so the
 * structure file is self-describing. The syncer's upsert collapses that
 * onto the same canonical ROLE_COMMANDANT row created earlier.
 */
final class AppRoleTreeSource implements RoleTreeSourceInterface
{
    public function getYamlPath(): string
    {
        return __DIR__ . '/../Resources/structure/roles.yml';
    }

    public function getRootParent(): ?string
    {
        return 'ROLE_ADMIN';
    }

    public function getOrder(): int
    {
        return 500;
    }
}
