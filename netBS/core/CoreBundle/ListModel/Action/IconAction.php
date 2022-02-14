<?php

namespace NetBS\CoreBundle\ListModel\Action;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

class IconAction extends LinkAction
{
    const ICON  = 'icon';

    public function __construct(RouterInterface $router)
    {
        parent::__construct($router);
    }

    public function configureOptions(OptionsResolver $resolver) {

        parent::configureOptions($resolver);

        $resolver->setDefault(self::ICON, 'fas fa-edit')
            ->setRequired(self::ROUTE);
    }

    public function render($item, $params = [])
    {
        if ($params[self::TEXT] !== null) return parent::render($item, $params);

        $params[LinkAction::TEXT] = "<i class='{$params['icon']}'></i>";
        return parent::render($item, $params);
    }
}
