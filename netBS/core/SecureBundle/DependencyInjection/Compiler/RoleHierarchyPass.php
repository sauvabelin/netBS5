<?php

namespace NetBS\SecureBundle\DependencyInjection\Compiler;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\SecureBundle\Service\NetBSRoleHierarchy;
use NetBS\SecureBundle\Voter\RoleVoter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RoleHierarchyPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $container->findDefinition('security.role_hierarchy')
            ->setClass(NetBSRoleHierarchy::class)
            ->setArguments([
                new Reference(EntityManagerInterface::class)
            ]);

        $container->findDefinition('security.access.simple_role_voter')
            ->setClass(RoleVoter::class);
    }
}
