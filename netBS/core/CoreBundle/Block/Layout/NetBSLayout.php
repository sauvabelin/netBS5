<?php

namespace NetBS\CoreBundle\Block\Layout;

use NetBS\CoreBundle\Block\LayoutConfigurator;
use NetBS\CoreBundle\Block\LayoutInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Twig\Environment;

class NetBSLayout implements LayoutInterface
{
    private $twig;

    public function __construct(Environment $twig_Environment)
    {
        $this->twig = $twig_Environment;
    }

    /**
     * Returns this layout's name
     * @return string
     */
    public function getName()
    {
        return 'netbs';
    }

    /**
     * Renders this layout with the given configuration
     * @param LayoutConfigurator $configurator
     * @param array $config
     * @return string
     */
    public function render(LayoutConfigurator $configurator, $config = [])
    {
        return $this->twig->render('@NetBSCore/block/netbs.layout.twig', [
            'config'    => $configurator,
            'title'     => $config['title']
        ]);
    }

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefault('title', null)
            ->setDefault('item', null);
    }
}
