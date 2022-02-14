<?php

namespace NetBS\CoreBundle\Controller;

use NetBS\CoreBundle\Event\ExtendMainMenuEvent;
use NetBS\CoreBundle\Menu\MainMenu;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Annotation\Route;

class MenuController extends AbstractController
{
    /**
     * @Route("/menu/render-main", name="netbs.core.menu.render_main")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function renderMainMenuAction(EventDispatcherInterface $dispatcher)
    {
        $menu       = new MainMenu();
        $dispatcher->dispatch(new ExtendMainMenuEvent($menu), ExtendMainMenuEvent::KEY);

        return $this->render('@NetBSCore/partial/menubar.partial.twig', array(
            'route' => $this->get('request_stack')->getParentRequest()->get('_route'),
            'menu'  => $menu
        ));
    }
}
