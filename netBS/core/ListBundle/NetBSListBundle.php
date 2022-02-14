<?php

namespace NetBS\ListBundle;

use NetBS\ListBundle\DependencyInjection\Compiler\RegisterColumnPass;
use NetBS\ListBundle\DependencyInjection\Compiler\RegisterListModelPass;
use NetBS\ListBundle\DependencyInjection\Compiler\RegisterRendererPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class NetBSListBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new RegisterListModelPass());
        $container->addCompilerPass(new RegisterRendererPass());
        $container->addCompilerPass(new RegisterColumnPass());
    }

}
