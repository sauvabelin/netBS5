<?php

namespace NetBS\CoreBundle\ListModel\Column;

use NetBS\ListBundle\Column\BaseColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PopoverColumn extends BaseColumn
{
    const   LABEL       = 'label';
    const   TITLE       = 'title';
    const   PLACEMENT   = 'placement';
    const   TRIGGER     = 'trigger';
    const   TYPE        = 'type';
    const   SPAN_CLASS  = 'span-class';
    const   CONTENT     = 'content';

    /**
     * Return content related to the given object with the given params
     * @param array $item
     * @param array $params
     * @return string
     */
    public function getContent($item, array $params = [])
    {
        $label      = is_callable($params[self::LABEL]) ? $params[self::LABEL]($item) : $params[self::LABEL];
        $class      = is_callable($params[self::SPAN_CLASS]) ? $params[self::SPAN_CLASS]($item) : $params[self::SPAN_CLASS];
        $content    = is_callable($params[self::CONTENT]) ? $params[self::CONTENT]($item) : $params[self::CONTENT];
        $title      = is_callable($params[self::TITLE]) ? $params[self::TITLE]($item) : $params[self::TITLE];
        $placement  = $params[self::PLACEMENT];
        $type       = $params[self::TYPE];
        $trigger    = $params[self::TRIGGER];

        if($type == 'tooltip')
            $title = $content;

        return "<span class='$class' data-html='true' data-toggle='$type' data-trigger='$trigger' data-placement='$placement' title='$title' data-content=\"$content\">$label</span>";
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefault(BaseColumn::SORTABLE, false)
            ->setDefault(self::PLACEMENT, 'top')
            ->setDefault(self::TRIGGER, 'hover')
            ->setDefault(self::TYPE, 'popover')
            ->setDefault(self::TITLE, null)
            ->setDefault(self::LABEL, "plusieurs données")
            ->setDefault(self::CONTENT, null)
            ->setDefault(self::SPAN_CLASS, 'label label-default')

            ->setAllowedValues(self::PLACEMENT, ['top', 'left', 'bottom', 'right'])
            ->setAllowedValues(self::TRIGGER, ['hover', 'click'])
            ->setAllowedValues(self::TYPE, ['popover', 'tooltip']);
    }
}