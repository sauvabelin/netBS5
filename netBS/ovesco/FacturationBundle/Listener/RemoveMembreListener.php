<?php

namespace Ovesco\FacturationBundle\Listener;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Event\RemoveFamilleEvent;
use NetBS\CoreBundle\Event\RemoveMembreEvent;
use NetBS\CoreBundle\Exceptions\UserConstraintException;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Subscriber\DoctrineDebiteurSubscriber;

class RemoveMembreListener
{
    public function __construct()
    {
    }

    public function onRemove(RemoveMembreEvent $event) {
        $this->checkNoOpenFactures($event->getMembre(), $event->getManager(), 'le membre');
    }

    public function onRemoveFamille(RemoveFamilleEvent $event) {
        $this->checkNoOpenFactures($event->getFamille(), $event->getManager(), 'la famille');
    }

    private function checkNoOpenFactures($debiteur, EntityManagerInterface $manager, string $label) {

        $debiteurId = DoctrineDebiteurSubscriber::createId($debiteur);

        $open = $manager->getRepository(Facture::class)->findBy([
            'debiteurId' => $debiteurId,
            'statut'     => Facture::OUVERTE,
        ]);

        if (count($open) > 0) {
            throw new UserConstraintException("Impossible de supprimer {$label} {$debiteur} : "
                . count($open) . " facture(s) ouverte(s) y sont attachée(s). "
                . "Veuillez les clôturer avant.");
        }
    }
}
