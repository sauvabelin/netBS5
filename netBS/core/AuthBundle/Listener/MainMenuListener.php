<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Listener;

use NetBS\CoreBundle\Event\ExtendMainMenuEvent;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class MainMenuListener
{
    public function __construct(private readonly TokenStorageInterface $storage)
    {
    }

    public function onMenuConfigure(ExtendMainMenuEvent $event): void
    {
        $token = $this->storage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof BaseUser || !$user->hasRole('ROLE_ADMIN')) {
            return;
        }

        $menu = $event->getMenu();
        $cat = $menu->getCategory('secure.admin')
            ?? $menu->registerCategory('secure.admin', 'Administration', 500);
        $cat->addSubMenu('secure.admin.auth', 'Authentication', 'fas fa-shield-alt')
            ->addSubLink('OIDC', 'auth.admin.oidc_clients.index')
            ->addSubLink('LDAP', 'auth.admin.ldap.index');
    }
}
