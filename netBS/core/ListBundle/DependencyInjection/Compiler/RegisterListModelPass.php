<?php

namespace NetBS\ListBundle\DependencyInjection\Compiler;

use NetBS\ListBundle\Model\ListModelInterface;
use NetBS\ListBundle\Service\ListManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterListModelPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager = $container->findDefinition(ListManager::class);

        foreach ($container->findTaggedServiceIds('netbs.list.model') as $id => $params) {

            $ref    = new \ReflectionClass($container->getDefinition($id)->getClass());

            if (!$ref->implementsInterface(ListModelInterface::class))
                throw new \InvalidArgumentException("List model $id must implement " . ListModelInterface::class . "!");

            $manager->addMethodCall('registerListModel', [$id, new Reference($id)]);
        }

    }
}
