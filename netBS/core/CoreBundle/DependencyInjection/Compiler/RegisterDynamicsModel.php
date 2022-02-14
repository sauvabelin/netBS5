<?php

namespace NetBS\CoreBundle\DependencyInjection\Compiler;

use NetBS\CoreBundle\Service\DynamicListManager;
use NetBS\CoreBundle\Service\ListBridgeManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterDynamicsModel implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager        = $container->getDefinition(DynamicListManager::class);
        $bridges        = $container->getDefinition(ListBridgeManager::class);

        foreach($container->findTaggedServiceIds('netbs.dynamic_model') as $id => $params)
            $manager->addMethodCall('registerModel', [new Reference($id)]);


        foreach($container->findTaggedServiceIds('netbs.bridge') as $id => $params)
            $bridges->addMethodCall('registerBridge', [new Reference($id)]);

        $bridges->addMethodCall('buildGraph');
    }
}
