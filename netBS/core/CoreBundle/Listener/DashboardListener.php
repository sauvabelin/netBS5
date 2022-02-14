<?php

namespace NetBS\CoreBundle\Listener;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Block\CardBlock;
use NetBS\CoreBundle\Event\PreRenderLayoutEvent;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DashboardListener
{
    protected $storage;

    protected $stack;

    protected $manager;

    public function __construct(TokenStorageInterface $tokenStorage, RequestStack $stack, EntityManagerInterface $manager)
    {
        $this->storage  = $tokenStorage;
        $this->stack    = $stack;
        $this->manager  = $manager;
    }

    public function preRender(PreRenderLayoutEvent $event) {

        if($this->stack->getCurrentRequest()->get('_route') !== "netbs.core.home.dashboard")
            return;

        $row        = $event->getConfigurator()->getRow(0);
        $user       = $this->storage->getToken()->getUser();
        $news       = $this->manager->getRepository('NetBSCoreBundle:News')->findForUser($user);
        $channels   = $this->manager->getRepository('NetBSCoreBundle:NewsChannel')->findWritableChannels($user);

        $row->addColumn(0, 4, 5, 12)->addRow()
            ->addColumn(0, 12)->setBlock(CardBlock::class, array(
                'title' => 'News',
                'subtitle' => 'Dernières news publiées',
                'template' => '@NetBSCore/news/news.block.twig',
                'params' => ['news' => $news, 'channels' => $channels]
            ));
    }
}
