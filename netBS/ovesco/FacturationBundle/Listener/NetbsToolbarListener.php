<?php

namespace Ovesco\FacturationBundle\Listener;

use NetBS\CoreBundle\Event\NetbsRendererToolbarEvent;
use NetBS\CoreBundle\ListModel\Renderer\BasicToolbarItem;
use NetBS\CoreBundle\Service\ListBridgeManager;
use NetBS\FichierBundle\Model\AdressableInterface;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Entity\Facture;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class NetbsToolbarListener
{
    private $bridgeManager;

    private $storage;

    private $twig;

    public function __construct(ListBridgeManager $bridgeManager, Environment $twig, TokenStorageInterface $storage)
    {
        $this->bridgeManager        = $bridgeManager;
        $this->twig                 = $twig;
        $this->storage              = $storage;
    }

    /**
     * @param NetbsRendererToolbarEvent $event
     * @throws \Exception
     */
    public function extend(NetbsRendererToolbarEvent $event) {

        if (!$this->storage->getToken()->getUser()->hasRole('ROLE_TRESORIER')) return;

        $itemClass      = $event->getTable()->getItemClass();

        $addCreances    = $itemClass !== Creance::class && $this->bridgeManager->isValidTransformation($itemClass, AdressableInterface::class);
        $addRappels     = $itemClass === Facture::class;
        $generate       = $itemClass === Creance::class;

        if(!$addCreances && !$generate)
            return;

        $content = $this->twig->render('@OvescoFacturation/renderer/facturation_toolbar.button.twig', [
            'table'         => $event->getTable(),
            'tableId'       => $event->getTableId(),
            'addCreances'   => $addCreances,
            'addRappels'    => $addRappels,
            'generate'      => $generate
        ]);

        $event->getToolbar()->addItem(new BasicToolbarItem($content, BasicToolbarItem::RIGHT));
    }
}
