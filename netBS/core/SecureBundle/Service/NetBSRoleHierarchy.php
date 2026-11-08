<?php

namespace NetBS\SecureBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\SecureBundle\Entity\Role;
use NetBS\SecureBundle\Mapping\BaseRole;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

class NetBSRoleHierarchy implements RoleHierarchyInterface
{
    private $roles;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->roles = $entityManager->getRepository(Role::class)->findAll();
    }

    /**
     * A role reaches itself and every role below it (its children, recursively).
     *
     * @param string[] $roles role names
     *
     * @return string[]
     */
    public function getReachableRoleNames(array $roles): array
    {
        $byName = [];
        foreach ($this->roles as $role) {
            $byName[$role->getRole()] = $role;
        }

        $reachable = [];
        foreach ($roles as $name) {
            $name = $name instanceof BaseRole ? $name->getRole() : (string) $name;
            if (!isset($byName[$name])) {
                $reachable[$name] = true;
                continue;
            }
            foreach ($byName[$name]->getChildrenRecursive() as $implied) {
                $reachable[$implied->getRole()] = true;
            }
        }

        return array_keys($reachable);
    }
}
