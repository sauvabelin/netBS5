<?php

namespace NetBS\CoreBundle\DependencyInjection\Compiler;

use NetBS\CoreBundle\Block\LayoutManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterLayoutPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definition = $container->findDefinition(LayoutManager::class);

        foreach($container->findTaggedServiceIds('netbs.block.layout') as $id => $p)
            $definition->addMethodCall('registerLayout', [new Reference($id)]);
    }
}
