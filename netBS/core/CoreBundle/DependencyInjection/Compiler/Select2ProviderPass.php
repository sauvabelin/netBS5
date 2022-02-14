<?php

namespace NetBS\CoreBundle\DependencyInjection\Compiler;

use NetBS\CoreBundle\Select2\Select2ProviderInterface;
use NetBS\CoreBundle\Select2\Select2ProviderManager;
use NetBS\CoreBundle\Subscriber\DoctrineMapperSubscriber;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class Select2ProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager    = $container->getDefinition(Select2ProviderManager::class);

        foreach($container->findTaggedServiceIds('netbs.select2_provider') as $id => $params) {

            $ref    = new \ReflectionClass($container->getDefinition($id)->getClass());

            if (!$ref->implementsInterface(Select2ProviderInterface::class))
                throw new \InvalidArgumentException("select2 provider $id must implement " . Select2ProviderInterface::class . "!");

            $manager->addMethodCall('registerProvider', [new Reference($id)]);
        }
    }
}
