<?php

namespace NetBS\CoreBundle\Block;

use Symfony\Component\OptionsResolver\OptionsResolver;

interface LayoutInterface
{
    /**
     * Returns this layout's name
     * @return string
     */
    public function getName();

    /**
     * Renders this layout with the given configuration
     * @param LayoutConfigurator $configurator
     * @param array $layoutConfig
     * @return string
     */
    public function render(LayoutConfigurator $configurator, $layoutConfig = []);

    /**
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver);
}