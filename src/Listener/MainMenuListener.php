<?php

namespace App\Listener;

use NetBS\CoreBundle\Event\ExtendMainMenuEvent;
use App\Entity\BSUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MainMenuListener
{
    private $storage;

    public function __construct(TokenStorageInterface $storage)
    {
        $this->storage      = $storage;
    }

    public function onMenuConfigure(ExtendMainMenuEvent $event)
    {
        /** @var BSUser $user */
        $menu   = $event->getMenu();
        $links  = $menu->getCategory('app')->getLinks();
        $user   = $this->storage->getToken()->getUser();

        foreach($links as $link)
            if($link->getKey() === 'fichier')
                $link->addSubLink('Ajouter un membre', 'sauvabelin.membre.add_membre');

        $adminCategory  = $menu->getCategory('secure.admin');

        if($user->hasRole("ROLE_SG")) {
            $adminCategory->getLink('netbs.secure.admin.users')
                ->addSubLink('Derniers comptes', 'sauvabelin.user.latest_created');
        }

        if($user->hasRole('ROLE_TRESORIER')) {
            $menu->getCategory('ovesco.facturation')->getLink('facturation.autre')
                ->addSubLink('Cotisations', 'netbs.core.automatic_list.view_list', ['alias' => 'sauvabelin.cotisations']);
        }
    }
}
