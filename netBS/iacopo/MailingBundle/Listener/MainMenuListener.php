<?php

namespace Iacopo\MailingBundle\Listener;

use NetBS\CoreBundle\Event\ExtendMainMenuEvent;
use NetBS\SecureBundle\Mapping\BaseUser;
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
        $menu = $event->getMenu();

        /** @var BaseUser $user */
        $user = $this->storage->getToken()->getUser();

        if (!$user->hasRole('ROLE_MAILING')) return;

        // Add to Administration category
        $adminCategory = $menu->getCategory('secure.admin');
        $adminCategory->addLink('mailing.dashboard', 'Listes de diffusion', 'fas fa-envelope', 'iacopo.mailing.list');
    }
}
