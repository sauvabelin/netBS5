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

        if ($user->hasRole('ROLE_IT')) {
            $menu->getCategory('secure.admin')->addLink('admin.news.bot', 'News Bots', 'fas fa-history', 'sauvabelin.news_channel_bot.manage');
        }
    }
}
