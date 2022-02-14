<?php

namespace NetBS\CoreBundle\Listener;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Event\ExtendMainMenuEvent;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MainMenuListener
{
    /**
     * @var TokenStorageInterface
     */
    private $storage;

    /**
     * @var EntityManagerInterface
     */
    private $manager;

    public function __construct(TokenStorageInterface $storage, EntityManagerInterface $manager)
    {
        $this->storage  = $storage;
        $this->manager  = $manager;
    }

    public function onMenuConfigure(ExtendMainMenuEvent $event)
    {
        $menu       = $event->getMenu();

        /** @var BaseUser $user */
        $user       = $this->storage->getToken()->getUser();

        $menu->registerCategory('app.home', 'Home', 3000)
            ->addLink('app.home.dashboard', 'Dashboard', 'fas fa-home', 'netbs.core.home.dashboard')
            ->addLink('netbs.core.news.read_news', 'Lire les news', 'fas fa-newspaper', 'netbs.core.news.read_news');

        $repo       = $this->manager->getRepository('NetBSCoreBundle:DynamicList');
        $lists      = $repo->findForUser($user);

        $menu->registerCategory('other', 'Autre');
        $categorie  = $menu->registerCategory('app', 'NetBS', 1000);
        $submenu    = $categorie->addSubMenu('app.lists', 'Listes', 'fas fa-list');
        $submenu
            ->addSubLink('Gérer mes listes', 'netbs.core.dynamics_list.manage_lists');

        if($user->hasRole('ROLE_READ_EVERYWHERE'))
            $submenu->addSubLink('Listes automatiques', 'netbs.core.automatic_list.view_lists');

        if(count($lists) > 0)
            foreach ($repo->findForUser($user) as $list)
                $submenu->addSubLink($list->getName() . " (" . count($list->_getItemIds()) . ")", 'netbs.core.dynamics_list.manage_list', array('id' => $list->getId()));

        $secureCat  = $menu->registerCategory('secure.admin', 'Administration', 500);

        if(!$user->hasRole('ROLE_SG'))
            return;

        $secureCat->addLink("admin.news", "Gestion des news", "fas fa-newspaper", "netbs.core.news.manage");
        $secureCat->addLink('secure.admin.changelog', 'Modifications', 'fas fa-history', 'netbs.core.changelog.list');

        if($user->hasRole("ROLE_ADMIN"))
            $secureCat->addLink('secure.admin.parameters', 'Paramètres', 'fas fa-cog', 'netbs.core.parameters.list');
    }
}
