<?php

namespace NetBS\CoreBundle\DependencyInjection\Compiler;

use NetBS\CoreBundle\Service\ListActionsManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterListActionsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager    = $container->findDefinition(ListActionsManager::class);

        foreach($container->findTaggedServiceIds('netbs.list_action') as $id => $params)
            $manager->addMethodCall('registerAction', [new Reference($id)]);
    }
}
