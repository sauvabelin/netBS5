<?php

namespace NetBS\ListBundle\DependencyInjection\Compiler;

use NetBS\ListBundle\Model\RendererInterface;
use NetBS\ListBundle\Service\RendererManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterRendererPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager = $container->findDefinition(RendererManager::class);

        foreach ($container->findTaggedServiceIds('netbs.list.renderer') as $id => $params) {

            $definiton      = $container->findDefinition($id);
            $rendererClass  = $definiton->getClass(); /** @var RendererInterface $rendererClass */
            $refClass       = new \ReflectionClass($rendererClass);

            if (!$refClass->implementsInterface(RendererInterface::class))
                throw new \InvalidArgumentException("Renderer $id must implement " . RendererInterface::class . "!");

            $manager->addMethodCall('registerRenderer', [$id, new Reference($id)]);
        }
    }
}
