<?php

namespace NetBS\CoreBundle\DependencyInjection\Compiler;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Service\LoggerManager;
use NetBS\FichierBundle\LogRepresenter\FichierRepresenter;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterLogRepresentersPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager    = $container->getDefinition(LoggerManager::class);
        $manager->addMethodCall('setEntityManager', [new Reference(EntityManagerInterface::class)]);

        foreach($container->findTaggedServiceIds('netbs.log_representer') as $serviceId => $params) {
            $manager->addMethodCall('registerRepresenter', [new Reference($serviceId)]);

            $definition = $container->getDefinition($serviceId);

            if(is_subclass_of($definition->getClass(), FichierRepresenter::class)) {
                $definition->addMethodCall('setConfig', [new Reference(FichierConfig::class)]);
                $definition->addMethodCall('setTwig', [new Reference('twig')]);
            }
        }
    }
}
