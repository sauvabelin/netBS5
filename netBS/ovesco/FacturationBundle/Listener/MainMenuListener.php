<?php

namespace Ovesco\FacturationBundle\Listener;

use NetBS\CoreBundle\Event\ExtendMainMenuEvent;
use NetBS\SecureBundle\Mapping\BaseUser;
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
        $menu   = $event->getMenu();
        /** @var BaseUser $user */
        $user   = $this->storage->getToken()->getUser();
        if (!$user->hasRole('ROLE_TRESORIER')) return;
        $category = $menu->registerCategory('ovesco.facturation', 'Facturation');

        // $category->addLink('facturation.dashboard', 'Administration', 'fas fa-money-bill-alt', 'ovesco.facturation.dashboard');

        $listes = $category->addSubMenu('facturation.lists', 'Listes de factures', 'fas fa-file-alt');
        $listes->addSubLink('En attente de paiement', 'ovesco.facturation.facture.attente_paiement');
        $listes->addSubLink("En attente d'impression", 'ovesco.facturation.facture.attente_impression');
        $listes->addSubLink("Créances Ouvertes", "ovesco.facturation.creance.find_ouvertes");

        $search = $category->addSubMenu('facturation.search', 'Rechercher', 'fas fa-search');
        $search->addSubLink('Créances', 'ovesco.facturation.search_creances');
        $search->addSubLink('Factures', 'ovesco.facturation.search_factures');
        $search->addSubLink('Paiements', 'ovesco.facturation.search_paiements');
        $autre = $category->addSubMenu('facturation.autre', 'Autre', 'fas fa-bomb');
        $autre->addSubLink('Liste des comptes', 'ovesco.facturation.compte.list');
        $autre->addSubLink('Importer un fichier BVR', 'ovesco.facturation.camt.import');
        $autre->addSubLink('Modèles de facture', 'ovesco.facturation.facture_model.list');
    }
}
