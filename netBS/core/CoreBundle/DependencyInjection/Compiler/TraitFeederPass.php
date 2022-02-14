<?php

namespace NetBS\CoreBundle\DependencyInjection\Compiler;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\CoreBundle\Utils\DIHelper;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\ParamTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\CoreBundle\Utils\Traits\SessionTrait;
use NetBS\CoreBundle\Utils\Traits\TokenTrait;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\FichierBundle\Utils\Traits\SecureConfigTrait;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class TraitFeederPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $services   = array_merge(
            $container->findTaggedServiceIds('netbs.select2_provider'),
            $container->findTaggedServiceIds('netbs.list.model'),
            $container->findTaggedServiceIds('netbs.loader')
        );

        foreach($services as $id => $p) {

            $definiton      = $container->findDefinition($id);
            $modelClass     = $definiton->getClass();
            $refClass       = new \ReflectionClass($modelClass);
            $traits         = array_unique($this->findTraitsRecursive($refClass));

            if(in_array(EntityManagerTrait::class, $traits))
                $definiton->addMethodCall('setEntityManager', [new Reference(EntityManagerInterface::class)]);

            if(in_array(SessionTrait::class, $traits))
                $definiton->addMethodCall('setSession', [new Reference(SessionInterface::class)]);

            if(in_array(RouterTrait::class, $traits))
                $definiton->addMethodCall('setRouter', [new Reference(RouterInterface::class)]);

            if(in_array(TokenTrait::class, $traits))
                $definiton->addMethodCall('setTokenStorage', [new Reference(TokenStorageInterface::class)]);

            if(in_array(ParamTrait::class, $traits))
                $definiton->addMethodCall('setParameterManager', [new Reference(ParameterManager::class)]);

            if(in_array(FichierConfigTrait::class, $traits))
                $definiton->addMethodCall('setFichierConfig', [new Reference(FichierConfig::class)]);

            if(in_array(SecureConfigTrait::class, $traits))
                $definiton->addMethodCall('setSecureConfig', [new Reference(SecureConfig::class)]);
        }
    }

    private function findTraitsRecursive(\ReflectionClass $refClass) {
        $traits = DIHelper::getTraits($refClass);

        foreach($traits as $trait)
            $traits = array_merge($traits, $this->findTraitsRecursive(new \ReflectionClass($trait)));
        return $traits;
    }
}
