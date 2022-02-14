<?php

namespace NetBS\CoreBundle\DependencyInjection\Compiler;

use NetBS\CoreBundle\Exporter\CSVExporter;
use NetBS\CoreBundle\Exporter\PDFExporter;
use NetBS\CoreBundle\Service\ExporterManager;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterExporterPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager    = $container->getDefinition(ExporterManager::class);

        foreach($container->findTaggedServiceIds('netbs.exporter') as $serviceId => $params) {

            $definition = $container->getDefinition($serviceId);
            $class      = $definition->getClass();

            if(is_subclass_of($class, CSVExporter::class)) {

                $definition->addMethodCall('setAccessor', [new Reference('property_accessor')]);
            }

            $manager->addMethodCall('registerExporter', [new Reference($serviceId)]);
        }
    }
}
