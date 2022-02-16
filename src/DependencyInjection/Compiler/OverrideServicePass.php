<?php

namespace App\DependencyInjection\Compiler;

use App\Exporter\EtiquettesV2Exporter;
use App\Imagine\GalerieLoader;
use App\ListModel\BSUserList;
use App\LogRepresenter\MembreRepresenter;
use App\Model\GalerieConfig;
use App\Searcher\BSMembreSearcher;
use App\Service\UserManager;
use NetBS\FichierBundle\Exporter\PDFEtiquettesV2;
use NetBS\FichierBundle\LogRepresenter\MembreLogRepresenter;
use NetBS\FichierBundle\Searcher\MembreSearcher;
use NetBS\SecureBundle\ListModel\UsersList;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Definition;

class OverrideServicePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $container->getDefinition(MembreLogRepresenter::class)->setClass(MembreRepresenter::class);
        $container->getDefinition(UsersList::class)->setClass(BSUserList::class);
        $container->getDefinition(MembreSearcher::class)->setClass(BSMembreSearcher::class);
        $container->getDefinition(\NetBS\SecureBundle\Service\UserManager::class)->setClass(UserManager::class);
        $container->getDefinition('liip_imagine.binary.loader.prototype.filesystem')->setClass(GalerieLoader::class);

        $container->getDefinition(PDFEtiquettesV2::class)->setClass(EtiquettesV2Exporter::class);

        $configDef = new Definition(GalerieConfig::class);
        $configDef->setArguments([
           $container->getParameter('kernel.project_dir'),
           $container->resolveEnvPlaceholders('%env(string:GALERIE_PREFIX_DIRECTORY)%'),
           $container->resolveEnvPlaceholders('%env(string:GALERIE_MAPPED_DIRECTORY)%'),
           $container->resolveEnvPlaceholders('%env(string:GALERIE_CACHE_DIRECTORY)%'),
           $container->resolveEnvPlaceholders('%env(json:GALERIE_IMAGE_EXTENSIONS)%'),
           $container->resolveEnvPlaceholders('%env(json:GALERIE_DESCRIPTION_FILENAMES)%'),
        ]);

        $container->setDefinition(GalerieConfig::class, $configDef);
    }
}
