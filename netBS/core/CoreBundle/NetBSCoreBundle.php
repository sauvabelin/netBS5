<?php

namespace NetBS\CoreBundle;

use NetBS\CoreBundle\DependencyInjection\Compiler\MassUpdaterPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterAutomaticListsPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterBlockPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterDeleterPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterDynamicsModel;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterExporterPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterHelpersPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterLayoutPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterListActionsPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterLoaderPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterLogRepresentersPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterPostInstallScriptsPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterPreviewersPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterSearcherPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\RegisterTypesPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\Select2ProviderPass;
use NetBS\CoreBundle\DependencyInjection\Compiler\TraitFeederPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class NetBSCoreBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new MassUpdaterPass());
        $container->addCompilerPass(new RegisterAutomaticListsPass());
        $container->addCompilerPass(new RegisterBlockPass());
        $container->addCompilerPass(new RegisterDynamicsModel());
        $container->addCompilerPass(new RegisterExporterPass());
        $container->addCompilerPass(new RegisterHelpersPass());
        $container->addCompilerPass(new RegisterLayoutPass());
        $container->addCompilerPass(new RegisterSearcherPass());
        $container->addCompilerPass(new Select2ProviderPass());
        $container->addCompilerPass(new TraitFeederPass());
        $container->addCompilerPass(new RegisterLogRepresentersPass());
        $container->addCompilerPass(new RegisterPreviewersPass());
        $container->addCompilerPass(new RegisterPostInstallScriptsPass());
        $container->addCompilerPass(new RegisterListActionsPass());
        $container->addCompilerPass(new RegisterDeleterPass());
        $container->addCompilerPass(new RegisterLoaderPass());
        $container->addCompilerPass(new RegisterTypesPass());
    }

}
