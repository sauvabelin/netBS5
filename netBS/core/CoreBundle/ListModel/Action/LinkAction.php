<?php

namespace NetBS\CoreBundle\ListModel\Action;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class LinkAction implements ActionInterface
{
    const TEXT      = 'text';
    const THEME     = 'theme';
    const ROUTE     = 'route';
    const SIZE      = 'size';
    const CLASSE    = 'class';
    const ATTRS     = 'attrs';
    const TAG       = 'tag';
    const TITLE     = 'title';

    protected $router;

    public function __construct(RouterInterface $router)
    {
        $this->router   = $router;
    }

    public function configureOptions(OptionsResolver $resolver) {

        $resolver->setDefault(self::TEXT, null)
            ->setDefault(self::THEME, 'secondary')
            ->setDefault(self::SIZE, 'btn-xs')
            ->setDefault(self::CLASSE, '')
            ->setDefault(self::TAG, 'a')
            ->setDefault(self::ATTRS, '')
            ->setDefault(self::TITLE, null)
            ->setRequired(self::ROUTE);
    }

    public function render($item, $params = [])
    {
        $route  = is_string($params[self::ROUTE]) ? $params[self::ROUTE] : ($params[self::ROUTE])($item);
        $href   = $params[self::TAG] === 'a' ? "href='$route'" : "";
        $title  = $params[self::TITLE] === null ? '' : "title='{$params[self::TITLE]}'";

        return "<{$params[self::TAG]} $href {$params[self::ATTRS]} $title class='btn {$params[self::SIZE]}"
            . " btn-{$params[self::THEME]} {$params[self::CLASSE]}'>{$params[self::TEXT]}</{$params[self::TAG]}>";
    }
}
