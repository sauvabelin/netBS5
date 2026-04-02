<?php

namespace NetBS\CoreBundle\Controller;

use NetBS\CoreBundle\Event\ExtendMainMenuEvent;
use NetBS\CoreBundle\Menu\MainMenu;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;

class MenuController extends AbstractController
{
    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/menu/render-main', name: 'netbs.core.menu.render_main')]
    public function renderMainMenuAction(EventDispatcherInterface $dispatcher, RequestStack $requestStack)
    {
        $menu       = new MainMenu();
        $dispatcher->dispatch(new ExtendMainMenuEvent($menu), ExtendMainMenuEvent::KEY);

        return $this->render('@NetBSCore/partial/menubar.partial.twig', array(
            'route' => $requestStack->getParentRequest()->get('_route'),
            'menu'  => $menu
        ));
    }
}
