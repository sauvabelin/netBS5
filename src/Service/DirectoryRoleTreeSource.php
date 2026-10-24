<?php

declare(strict_types=1);

namespace App\Service;

use NetBS\SecureBundle\Service\RoleTreeSourceInterface;

/**
 * Directory admin roles (`ROLE_NEXTCLOUD_ADMIN`, `ROLE_WIKI_ADMIN`) as root
 * roles. Kept out of {@see AppRoleTreeSource}, whose roles are grafted under
 * ROLE_ADMIN and would therefore be held by every netBS admin.
 */
final class DirectoryRoleTreeSource implements RoleTreeSourceInterface
{
    public function getYamlPath(): string
    {
        return __DIR__ . '/../Resources/structure/directory_roles.yml';
    }

    public function getRootParent(): ?string
    {
        return null;
    }

    public function getOrder(): int
    {
        return 600;
    }
}
