<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Service;

/**
 * Source for the minimal system role tree (ROLE_ADMIN → ROLE_USER) defined
 * by SecureBundle. Runs first so other bundles can graft onto ROLE_ADMIN.
 */
final class SystemRoleTreeSource implements RoleTreeSourceInterface
{
    public function getYamlPath(): string
    {
        return __DIR__ . '/../Resources/security/system_roles.yml';
    }

    public function getRootParent(): ?string
    {
        return null;
    }

    public function getOrder(): int
    {
        return 1;
    }
}
