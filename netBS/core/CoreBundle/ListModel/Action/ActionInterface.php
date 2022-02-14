<?php

namespace NetBS\CoreBundle\ListModel\Action;

use Symfony\Component\OptionsResolver\OptionsResolver;

interface ActionInterface
{
    /**
     * @param OptionsResolver $resolver
     * @return mixed
     */
    public function configureOptions(OptionsResolver $resolver);

    /**
     * Renders the action button corresponding
     * @param object $item
     * @param array $params
     * @return string
     */
    public function render($item, $params = []);
}