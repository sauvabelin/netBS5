<?php

namespace NetBS\CoreBundle\DependencyInjection\Compiler;

use NetBS\CoreBundle\Service\DeleterManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterDeleterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager = $container->getDefinition(DeleterManager::class);

        foreach($container->findTaggedServiceIds('netbs.deleter') as $id => $params) {
            $manager->addMethodCall('registerDeleter', [new Reference($id)]);
            $container->findDefinition($id)->addMethodCall('setManager', [new Reference('doctrine.orm.default_entity_manager')]);
        }
    }
}
