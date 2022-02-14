<?php

namespace NetBS\CoreBundle\DependencyInjection\Compiler;

use NetBS\CoreBundle\Select2\Select2ProviderInterface;
use NetBS\CoreBundle\Select2\Select2ProviderManager;
use NetBS\CoreBundle\Service\FormTypesRegistrer;
use NetBS\CoreBundle\Subscriber\DoctrineMapperSubscriber;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

class RegisterTypesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $manager    = $container->getDefinition(FormTypesRegistrer::class);

        foreach($container->findTaggedServiceIds('form.type') as $id => $params) {
            $manager->addMethodCall('addType', [new Reference($id)]);
        }
    }
}
