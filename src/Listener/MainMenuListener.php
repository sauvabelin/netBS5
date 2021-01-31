<?php

namespace App\Listener;

use NetBS\CoreBundle\Event\ExtendMainMenuEvent;
use NetBS\SecureBundle\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;

class MainMenuListener
{
    /**
     * @var TokenStorage
     */
    private $storage;

    public function __construct(TokenStorage $storage)
    {
        $this->storage      = $storage;
    }

    public function onMenuConfigure(ExtendMainMenuEvent $event)
    {
        /** @var User $user */
        $menu   = $event->getMenu();
        $links  = $menu->getCategory('app')->getLinks();
        $user   = $this->storage->getToken()->getUser();

        foreach($links as $link)
            if($link->getKey() === 'fichier')
                $link->addSubLink('Ajouter un membre', 'tdgl.membre.add_membre');
    }
}
