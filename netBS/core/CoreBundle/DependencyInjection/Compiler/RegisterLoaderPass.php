<?php

namespace NetBS\CoreBundle\DependencyInjection\Compiler;

use NetBS\CoreBundle\Service\LoaderManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterLoaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager    = $container->getDefinition(LoaderManager::class);

        foreach($container->findTaggedServiceIds('netbs.loader') as $serviceId => $params) {
            $manager->addMethodCall('registerLoader', [new Reference($serviceId)]);
        }

    }
}
