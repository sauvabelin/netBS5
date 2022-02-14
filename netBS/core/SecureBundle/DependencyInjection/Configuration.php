<?php

namespace NetBS\SecureBundle\DependencyInjection;

use NetBS\SecureBundle\Entity\Autorisation;
use NetBS\SecureBundle\Entity\Role;
use NetBS\SecureBundle\Entity\User;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files.
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/configuration.html}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('netbs_secure');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('entities')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('role_class')->defaultValue(Role::class)->end()
                        ->scalarNode('user_class')->defaultValue(User::class)->end()
                        ->scalarNode('autorisation_class')->defaultValue(Autorisation::class)->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
