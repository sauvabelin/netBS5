<?php

namespace App\Listener;

use NetBS\CoreBundle\Event\ExtendMainMenuEvent;
use NetBS\SecureBundle\Entity\User;

class MainMenuListener
{

    public function onMenuConfigure(ExtendMainMenuEvent $event)
    {
        /** @var User $user */
        $menu   = $event->getMenu();
        $links  = $menu->getCategory('app')->getLinks();

        foreach($links as $link)
            if($link->getKey() === 'fichier')
                $link->addSubLink('Ajouter un membre', 'tdgl.membre.add_membre');
    }
}
