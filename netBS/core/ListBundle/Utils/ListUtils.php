<?php

namespace NetBS\ListBundle\Utils;

use NetBS\ListBundle\Model\ListModelInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ListUtils
{
    public static function extractParameters(ListModelInterface $listModel) {

        $parameters = [];
        $options    = new OptionsResolver();
        $listModel->configureOptions($options);

        foreach($options->getDefinedOptions() as $option)
            $parameters[$option] = $listModel->getParameter($option);

        return $parameters;
    }

    public static function toArray(ListModelInterface $listModel) {

        return [

            'alias'             => $listModel->getAlias(),
            'class'             => get_class($listModel),
            'managedItemsClass' => $listModel->getManagedItemsClass(),
            'parameters'        => self::extractParameters($listModel)
        ];
    }
}