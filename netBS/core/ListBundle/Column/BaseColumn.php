<?php

namespace NetBS\ListBundle\Column;

use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class BaseColumn
{
    const SORTABLE  = 'sortable';

    /**
     * Return content related to the given object with the given params
     * @param object $item
     * @param array $params
     * @return string
     */
    abstract public function getContent($item, array $params = []);

    /**
     * Configures this column's required configuration
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setDefault('sortable', true);
    }
}