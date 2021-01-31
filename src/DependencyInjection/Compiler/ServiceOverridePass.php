<?php

namespace App\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use App\ListModel\TDGLUserList;
use App\Searcher\TDGLMembreSearcher;

class ServiceOverridePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $container->getDefinition('netbs.fichier.searcher.membres')->setClass(TDGLMembreSearcher::class);
        $container->getDefinition('netbs.secure.list.users')->setClass(TDGLUserList::class);
    }
}
