<?php

namespace NetBS\CoreBundle\ListModel\Column;

use NetBS\ListBundle\Column\BaseColumn;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class LinkColumn extends BaseColumn
{
    const ROUTE = 'route';
    const LABEL = 'label';

    protected $router;

    public function __construct(RouterInterface $router)
    {
        $this->router   = $router;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setDefault(BaseColumn::SORTABLE, false)
            ->setRequired(self::ROUTE)
            ->setRequired(self::LABEL);
    }

    public function getContent($item, array $params = [])
    {
        if(!$item)
            return '';

        $route  = $params[self::ROUTE];
        $text   = $params[self::LABEL];
        $path   = is_string($route) ? $this->router->generate($route) : $route($item, $this->router);
        $label  = is_string($text)  ? $text : $text($item);

        return "<a href='$path'>$label</a>";
    }
}
