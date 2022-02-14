<?php

namespace NetBS\CoreBundle\Block;

use Symfony\Component\OptionsResolver\OptionsResolver;

interface BlockInterface
{
    /**
     * Configures all options required by this block to render itself
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver);

    /**
     * Renders the block
     * @param array $params
     * @return string
     */
    public function render(array $params = []);
}