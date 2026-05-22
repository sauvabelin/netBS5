<?php

declare(strict_types=1);

namespace NetBS\FichierBundle\Service;

use NetBS\SecureBundle\Service\RoleTreeSourceInterface;

/**
 * Grafts the generic file-domain role subtree (ROLE_SG, ROLE_IT, and the
 * CRUD chain under ROLE_SG) onto an app-specified parent role.
 *
 * The parent defaults to ROLE_COMMANDANT (the conventional top-of-org role)
 * but can be overridden via the constructor in the app's services config —
 * useful when an org wants a more specific role (e.g. ROLE_QM) to inherit
 * the full file-domain permissions.
 *
 * Order 600 ensures App's source (order 500) has run first and created the
 * graft-target role before this source tries to look it up.
 */
final class FichierRoleTreeSource implements RoleTreeSourceInterface
{
    public function __construct(private readonly string $rootParent = 'ROLE_COMMANDANT')
    {
    }

    public function getYamlPath(): string
    {
        return __DIR__ . '/../Resources/security/roles.yml';
    }

    public function getRootParent(): ?string
    {
        return $this->rootParent;
    }

    public function getOrder(): int
    {
        return 600;
    }
}
