<?php

namespace NetBS\CoreBundle\DependencyInjection\Compiler;

use NetBS\CoreBundle\Searcher\SearcherManager;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\CoreBundle\Service\QueryMaker;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterSearcherPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager    = $container->getDefinition(SearcherManager::class);
        $binder     = $container->getDefinition(QueryMaker::class);

        foreach($container->findTaggedServiceIds('netbs.searcher') as $serviceId => $params) {

            $searcher   = $container->getDefinition($serviceId);

            $searcher->addMethodCall('setQueryMaker', [new Reference(QueryMaker::class)]);
            $searcher->addMethodCall('setParameterManager', [new Reference(ParameterManager::class)]);
            $manager->addMethodCall('registerSearcher', [new Reference($serviceId)]);
        }

        foreach($container->findTaggedServiceIds('netbs.searcher.binder') as $serviceId => $p)
            $binder->addMethodCall('registerBinder', [new Reference($serviceId)]);
    }
}
