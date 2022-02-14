<?php

namespace NetBS\CoreBundle\ListModel\Action;

use NetBS\CoreBundle\ListModel\Column\LinkColumn;
use NetBS\CoreBundle\Twig\Extension\AssetsExtension;
use Symfony\Bridge\Twig\Extension\AssetExtension;
use Symfony\Component\Routing\RouterInterface;

class ModalAction extends IconAction
{
    private $registrer;

    private $assets;

    public function __construct(AssetExtension $asset, AssetsExtension $registrer, RouterInterface $router)
    {
        parent::__construct($router);
        $this->registrer = $registrer;
        $this->assets = $asset;
    }

    public function render($item, $params = [])
    {
        $this->registrer->registerJs($this->assets->getAssetUrl('bundles/netbscore/js/modal.js'));

        $route  = is_string($params[LinkColumn::ROUTE])
            ? $params[LinkColumn::ROUTE]
            : ($params[LinkColumn::ROUTE])($item);

        $params[LinkAction::ROUTE]  = "#";
        $params[LinkAction::TAG]    = 'btn';
        $params[LinkAction::ATTRS]  = $params[LinkAction::ATTRS] . " data-modal data-modal-url='$route'";

        return parent::render($item, $params);
    }
}
