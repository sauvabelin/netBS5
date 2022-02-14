<?php

namespace NetBS\SecureBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\SecureBundle\Mapping\BaseRole;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

class NetBSRoleHierarchy implements RoleHierarchyInterface
{
    private $roles;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->roles = $entityManager->getRepository('NetBSSecureBundle:Role')->findAll();
    }

    /**
     * @param BaseRole[] $roles
     *
     * @return string[]
     */
    public function getReachableRoleNames(array $roles): array
    {
        $reachable = [];
        foreach ($this->roles as $current) {
            $stack = [];
            $parent = $current;
            while ($parent !== null) {
                $stack[] = $parent->getRole();
                if ($parent->getRole() === $current->getRole()) {
                    $reachable = array_unique(array_merge($reachable, $stack));
                    break;
                }
            }
        }
        return $reachable;
    }
}
