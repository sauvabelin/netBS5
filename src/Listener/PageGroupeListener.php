<?php

namespace App\Listener;

use NetBS\CoreBundle\Block\TemplateBlock;
use NetBS\CoreBundle\Event\PreRenderLayoutEvent;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class PageGroupeListener
{
    protected $twig;

    protected $stack;

    protected $params;

    public function __construct(RequestStack $stack, ParameterManager $params, Environment $twig)
    {
        $this->twig     = $twig;
        $this->stack    = $stack;
        $this->params   = $params;
    }

    /**
     * @param PreRenderLayoutEvent $event
     * @throws \Exception
     */
    public function extendsPageGroupe(PreRenderLayoutEvent $event) {

        $route  = $this->stack->getCurrentRequest()->get('_route');

        if($route !== "netbs.fichier.groupe.page_groupe")
            return;

        /** @var BaseGroupe $groupe */
        $groupe         = $event->getParameters()['item'];
        $categorieId    = $groupe->getGroupeType()->getGroupeCategorie()->getId();
        $typeId         = $groupe->getGroupeType()->getId();
        $uniteId        = $this->params->getValue('bs', 'groupe_categorie.unite_id');
        $brancheId      = $this->params->getValue('bs', 'groupe_type.branche_id');
        $config         = $event->getConfigurator();
        $column         = $config->getRow(0)->getColumns()[0];

        if($categorieId === intval($uniteId) || $typeId === intval($brancheId)) {

            $column->addRow()->addColumn(0, 12)->setBlock(TemplateBlock::class, [
                'template' => 'block/etiquettes_groupe.block.twig',
                'params' => [
                    'groupe' => $groupe
                ]
            ]);

            if($categorieId === intval($uniteId)) {
                $column->addRow()->addColumn(0, 12)->setBlock(TemplateBlock::class, [
                    'template' => 'block/liste_unite.block.twig',
                    'params' => [
                        'groupe' => $groupe
                    ]
                ]);
            }
        }


        $column->addRow()->addColumn(0, 12)->setBlock(TemplateBlock::class, [
            'template' => 'block/rega_groupe.block.twig',
            'params' => [
                'groupe' => $groupe
            ]
        ]);
    }
}
