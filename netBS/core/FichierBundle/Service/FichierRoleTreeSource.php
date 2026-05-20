<?php

declare(strict_types=1);

namespace NetBS\FichierBundle\Service;

use NetBS\SecureBundle\Service\RoleTreeSourceInterface;

/**
 * Grafts the generic file-domain role subtree (ROLE_SG, ROLE_IT, and the
 * CRUD chain under ROLE_SG) onto ROLE_COMMANDANT.
 *
 * ROLE_COMMANDANT itself is org-specific and is declared by the app-level
 * source (src/Resources/structure/roles.yml). Order 600 ensures App's
 * source (order 500) has run first and created ROLE_COMMANDANT before this
 * source tries to look it up as a graft point.
 */
final class FichierRoleTreeSource implements RoleTreeSourceInterface
{
    public function getYamlPath(): string
    {
        return __DIR__ . '/../Resources/security/roles.yml';
    }

    public function getRootParent(): ?string
    {
        return 'ROLE_COMMANDANT';
    }

    public function getOrder(): int
    {
        return 600;
    }
}
