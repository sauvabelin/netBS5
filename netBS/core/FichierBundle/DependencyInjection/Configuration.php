<?php

namespace NetBS\FichierBundle\DependencyInjection;

use NetBS\FichierBundle\Entity\Adresse;
use NetBS\FichierBundle\Entity\Attribution;
use NetBS\FichierBundle\Entity\ContactInformation;
use NetBS\FichierBundle\Entity\Distinction;
use NetBS\FichierBundle\Entity\Email;
use NetBS\FichierBundle\Entity\Famille;
use NetBS\FichierBundle\Entity\Fonction;
use NetBS\FichierBundle\Entity\Geniteur;
use NetBS\FichierBundle\Entity\Groupe;
use NetBS\FichierBundle\Entity\GroupeCategorie;
use NetBS\FichierBundle\Entity\GroupeType;
use NetBS\FichierBundle\Entity\Membre;
use NetBS\FichierBundle\Entity\ObtentionDistinction;
use NetBS\FichierBundle\Entity\Telephone;
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
        $treeBuilder = new TreeBuilder('netbs_fichier');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('entities')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('membre_class')->defaultValue(Membre::class)->end()
                        ->scalarNode('famille_class')->defaultValue(Famille::class)->end()
                        ->scalarNode('attribution_class')->defaultValue(Attribution::class)->end()
                        ->scalarNode('obtention_distinction_class')->defaultValue(ObtentionDistinction::class)->end()
                        ->scalarNode('fonction_class')->defaultValue(Fonction::class)->end()
                        ->scalarNode('groupe_class')->defaultValue(Groupe::class)->end()
                        ->scalarNode('distinction_class')->defaultValue(Distinction::class)->end()
                        ->scalarNode('geniteur_class')->defaultValue(Geniteur::class)->end()
                        ->scalarNode('groupe_categorie_class')->defaultValue(GroupeCategorie::class)->end()
                        ->scalarNode('groupe_type_class')->defaultValue(GroupeType::class)->end()
                        ->scalarNode('adresse_class')->defaultValue(Adresse::class)->end()
                        ->scalarNode('telephone_class')->defaultValue(Telephone::class)->end()
                        ->scalarNode('email_class')->defaultValue(Email::class)->end()
                        ->scalarNode('contact_information_class')->defaultValue(ContactInformation::class)->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
