<?php

namespace NetBS\CoreBundle\ListModel\Column;

use NetBS\ListBundle\Column\BaseColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ArrayColumn extends PopoverColumn
{
    const FORMATTING     = 'formatting';

    /**
     * Return content related to the given object with the given params
     * @param array $item
     * @param array $params
     * @return string
     */
    public function getContent($item, array $params = [])
    {
        $amount     = count($item);
        $theme      = $amount > 0 ? 'primary' : 'default';
        $class      = 'label label-' . $theme;
        $fn         = $params[self::FORMATTING];
        $content    = '';

        foreach($item as $value)
            $content .= $fn($value) . "</br>";

        $params[self::CONTENT]      = $content;
        $params[self::SPAN_CLASS]   = $class;
        $params[self::LABEL]        = $amount . " valeur" . ($amount > 1 ? 's' : '');

        return parent::getContent($item, $params);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);
        $resolver->setRequired(self::FORMATTING)
            ->setDefault(BaseColumn::SORTABLE, false);
    }
}