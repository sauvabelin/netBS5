<?php

namespace NetBS\CoreBundle\DependencyInjection\Compiler;

use NetBS\CoreBundle\Service\PreviewerManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterPreviewersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager    = $container->getDefinition(PreviewerManager::class);

        foreach($container->findTaggedServiceIds('netbs.previewer') as $serviceId => $p)
            $manager->addMethodCall('registerPreviewer', [new Reference($serviceId)]);
    }
}
