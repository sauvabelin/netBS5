<?php

namespace Ovesco\FacturationBundle\Listener;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Event\RemoveFamilleEvent;
use NetBS\CoreBundle\Event\RemoveMembreEvent;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Subscriber\DoctrineDebiteurSubscriber;

class RemoveMembreListener
{
    public function __construct()
    {
    }

    public function onRemove(RemoveMembreEvent $event) {
        $this->checkNoAttachedFactures($event->getMembre(), $event->getManager(), 'le membre');
    }

    public function onRemoveFamille(RemoveFamilleEvent $event) {
        $this->checkNoAttachedFactures($event->getFamille(), $event->getManager(), 'la famille');
    }

    private function checkNoAttachedFactures($debiteur, EntityManagerInterface $manager, string $label) {

        $debiteurId = DoctrineDebiteurSubscriber::createId($debiteur);

        $factures = $manager->getRepository(Facture::class)->findBy(['debiteurId' => $debiteurId]);
        $creances = $manager->getRepository(Creance::class)->findBy(['debiteurId' => $debiteurId]);

        if (count($factures) > 0 || count($creances) > 0) {
            throw new \ErrorException("Impossible de supprimer {$label} {$debiteur} : "
                . count($factures) . " facture(s) et " . count($creances) . " créance(s) y sont attachées. "
                . "Veuillez les supprimer avant.");
        }
    }
}
