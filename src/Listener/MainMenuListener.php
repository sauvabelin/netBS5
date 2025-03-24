<?php

namespace App\Listener;

use NetBS\CoreBundle\Event\ExtendMainMenuEvent;
use App\Entity\BSUser;
use App\Entity\Cabane;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MainMenuListener
{
    private $storage;

    private $em;

    public function __construct(TokenStorageInterface $storage, EntityManagerInterface $em)
    {
        $this->storage = $storage;
        $this->em      = $em;
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

        if ($user->hasRole("ROLE_APMBS")) {
            $apmbs = $menu->registerCategory("APMBS", "APMBS", 0);
            $apmbs->addLink("apmbs.reservations", "Réservations", "fas fa-calendar-alt", "sauvabelin.apmbs.reservations");
            $cabanes = $this->em->getRepository(Cabane::class)->findAll();
            foreach ($cabanes as $cabane) {
                $apmbs->addLink('apmbs.cabane.' . $cabane->getId(), $cabane->getNom(), 'fas fa-home', "sauvabelin.apmbs.cabane", ['id' => $cabane->getId()]);
            }

            $otherAPMBS = $apmbs->addSubMenu('apmbs.other', 'Autre', '');
            $otherAPMBS->addSubLink('Ajouter une cabane', 'sauvabelin.apmbs.add_cabane');
            $otherAPMBS->addSubLink("Intendants", "sauvabelin.apmbs.intendants");
            $otherAPMBS->addSubLink("Périodes de réservation", "sauvabelin.apmbs.time_periods");
        }
    }
}
