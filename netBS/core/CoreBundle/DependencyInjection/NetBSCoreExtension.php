<?php

namespace NetBS\CoreBundle\DependencyInjection;

use NetBS\CoreBundle\Mailer\MailChannel;
use NetBS\CoreBundle\Model\MailerConfig;
use NetBS\CoreBundle\Rule\LegacyRuleExpressions;
use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleContext;
use NetBS\CoreBundle\Rule\RuleOwnerLabeler;
use NetBS\CoreBundle\Settings\SettingsProvider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration.
 *
 * @link http://symfony.com/doc/current/cookbook/bundles/extension.html
 */
class NetBSCoreExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $container->registerForAutoconfiguration(SettingsProvider::class)
            ->addTag('netbs.settings_provider');
        $container->registerForAutoconfiguration(RuleContext::class)
            ->addTag('netbs.rule_context');
        $container->registerForAutoconfiguration(RuleBlock::class)
            ->addTag('netbs.rule_block');
        $container->registerForAutoconfiguration(RuleOwnerLabeler::class)
            ->addTag('netbs.rule_owner_labeler');
        $container->registerForAutoconfiguration(LegacyRuleExpressions::class)
            ->addTag('netbs.legacy_rule_expressions');
    }
}
