<?php

namespace NetBS\CoreBundle\DependencyInjection\Compiler;

use NetBS\CoreBundle\Model\Helper\BaseHelper;
use NetBS\CoreBundle\Service\HelperManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterHelpersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager    = $container->getDefinition(HelperManager::class);

        foreach($container->findTaggedServiceIds('netbs.helper') as $serviceId => $params) {

            $helper     = $container->findDefinition($serviceId);

            if(is_subclass_of($helper->getClass(), BaseHelper::class)) {

                $helper->addMethodCall('setTwig', [new Reference('twig')]);
                $helper->addMethodCall('setRouter', [new Reference('router')]);
            }

            $manager->addMethodCall('pushHelper', [new Reference($serviceId)]);
        }
    }
}
