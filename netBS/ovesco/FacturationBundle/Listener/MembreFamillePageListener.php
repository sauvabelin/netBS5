<?php

namespace Ovesco\FacturationBundle\Listener;

use NetBS\CoreBundle\Block\Model\Tab;
use NetBS\CoreBundle\Event\PreRenderLayoutEvent;
use Ovesco\FacturationBundle\Subscriber\DoctrineDebiteurSubscriber;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class MembreFamillePageListener
{
    protected $twig;

    protected $stack;

    protected $storage;

    public function __construct(RequestStack $stack, Environment $twig, TokenStorageInterface $tokenStorage)
    {
        $this->twig     = $twig;
        $this->stack    = $stack;
        $this->storage  = $tokenStorage;
    }

    /**
     * @param PreRenderLayoutEvent $event
     * @throws \Exception
     */
    public function extendsMembreFamillePage(PreRenderLayoutEvent $event) {

        $route  = $this->stack->getCurrentRequest()->get('_route');

        if (!in_array($route, ['netbs.fichier.membre.page_membre', 'netbs.fichier.famille.page_famille']))
            return;

        if (!$this->storage->getToken()->getUser()->hasRole('ROLE_TRESORIER')) return;
        $block = $event->getConfigurator()->getRow(0)->getColumn(1)->getRow(0)->getColumn(0)->getBlock();
        $tabs = $block->getParameters()->get('tabs');
        $tabs[] = $this->getTab($event);
        $block->getParameters()->set('tabs', $tabs);
    }

    private function getTab(PreRenderLayoutEvent $event) {
        $debiteur = $event->getParameter('item');
        $debiteurId = DoctrineDebiteurSubscriber::createId($debiteur);
        return new Tab("Facturation", "@OvescoFacturation/block/tabs/facturation_membre_famille.tab.twig", [
            'debiteurId' => $debiteurId,
        ]);
    }
}
