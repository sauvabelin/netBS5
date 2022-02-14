<?php

namespace NetBS\FichierBundle\Listener;

use NetBS\CoreBundle\Event\ExtendMainMenuEvent;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class MainMenuListener
{
    /**
     * @var TokenStorageInterface
     */
    private $storage;

    public function __construct(TokenStorageInterface $storage)
    {
        $this->storage  = $storage;
    }

    public function onMenuConfigure(ExtendMainMenuEvent $event)
    {
        /** @var BaseUser $user */
        $menu       = $event->getMenu();
        $user       = $this->storage->getToken()->getUser();
        $category   = $menu->getCategory('app');

        if($user->getMembre()) {

            $unitLink = $menu->getCategory('app.home')->addSubMenu('user.groupes', 'Mes unités', 'fas fa-cubes');

            foreach ($user->getMembre()->getActivesAttributions() as $attribution)
                $unitLink->addSubLink($attribution->getGroupe()->getNom(), 'netbs.fichier.groupe.page_groupe', ['id' => $attribution->getGroupe()->getId()]);
        }

        if($user->hasRole("ROLE_READ_EVERYWHERE"))
            $category
                ->addSubMenu('fichier', 'Fichier', 'fas fa-users')
                ->addSubLink('Rechercher des membres', 'netbs.fichier.membre.search')
                ->addSubLink('Liste des unités', 'netbs.fichier.groupe.page_groupes_hierarchy');

        if($user->hasRole("ROLE_SG")) {

            $category
                ->addSubMenu('fichier.structure', 'Structure', 'fas fa-th')
                ->addSubLink('Distinctions', 'netbs.fichier.distinction.page_distinctions')
                ->addSubLink("Types de groupes", 'netbs.fichier.groupe_type.page_groupe_types')
                ->addSubLink("Catégories de groupes", 'netbs.fichier.groupe_categorie.page_groupe_categories')
                ->addSubLink('Fonctions', 'netbs.fichier.fonction.page_fonctions');
        }
    }
}
