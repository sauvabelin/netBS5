<?php

namespace NetBS\CoreBundle\Event;

use NetBS\CoreBundle\Menu\MainMenu;
use Symfony\Contracts\EventDispatcher\Event;

class ExtendMainMenuEvent extends Event
{
    const KEY = 'netbs.menu.extend';

    protected $menu;

    public function __construct(MainMenu $menu)
    {
        $this->menu = $menu;
    }

    /**
     * @return MainMenu
     */
    public function getMenu()
    {
        return $this->menu;
    }
}
