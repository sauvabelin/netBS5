<?php

namespace NetBS\ListBundle\DependencyInjection\Compiler;

use NetBS\ListBundle\Service\ColumnManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterColumnPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager    = $container->findDefinition(ColumnManager::class);

        foreach($container->findTaggedServiceIds('netbs.list.column') as $serviceId => $params)
            $manager->addMethodCall('registerColumn', [new Reference($serviceId)]);
    }
}
