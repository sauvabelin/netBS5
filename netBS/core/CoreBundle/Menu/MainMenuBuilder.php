<?php

namespace NetBS\CoreBundle\Menu;

use NetBS\CoreBundle\Event\ExtendMainMenuEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class MainMenuBuilder {

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var TokenStorage
     */
    protected $tokenStorage;

    /**
     * @param EventDispatcher $dispatcher
     */
    public function __construct(EventDispatcher $dispatcher, TokenStorage $tokenStorage)
    {
        $this->dispatcher   = $dispatcher;
        $this->tokenStorage = $tokenStorage;
    }

    public function createMainMenu()
    {
        $menu   = new MainMenu();

        $this->dispatcher->dispatch(ExtendMainMenuEvent::KEY, new ExtendMainMenuEvent($menu));
        return $menu;
    }
}