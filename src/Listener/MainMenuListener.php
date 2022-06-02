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
        $this->storage = $storage;
    }

    public function onMenuConfigure(ExtendMainMenuEvent $event)
    {
        $menu   = $event->getMenu();
        $links  = $menu->getCategory('app')->getLinks();

        /** @var BSUser $user */
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

        if ($user->hasRole('ROLE_RESPONSABLE_COMM')) {
            $menu->getCategory('secure.admin')->addLink('admin.news.bot', 'News Bots', 'fas fa-robot', 'sauvabelin.news_channel_bot.manage');
        }

        if ($user->hasRole('ROLE_APMBS_RESERVATIONS')) {
            $apmbs = $menu->registerCategory('apmbs', 'APMBS');
            $apmbs->addLink('apmbs.reservations', 'Réservations', 'fas fa-book', 'sauvabelin.apmbs_reservations.dashboard');
            $apmbs->addLink('apmbs.cabanes', 'Cabanes', 'fas fa-home', 'sauvabelin.cabanes.dashboard');
        }
    }
}
