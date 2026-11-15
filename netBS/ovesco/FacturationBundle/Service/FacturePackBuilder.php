<?php

namespace Ovesco\FacturationBundle\Service;

use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Entity\Facture;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Turns loose créances into new factures: one facture per sendable address,
 * addressed to whoever owns that address. Nothing is persisted.
 */
class FacturePackBuilder
{
    /**
     * @param Creance[] $creances
     * @return array<int, Facture> keyed by address id
     * @throws AccessDeniedException when a créance already belongs to a facture
     * @throws \Exception when a debiteur has no sendable address
     */
    public function build(iterable $creances): array
    {
        $factures = [];
        foreach ($creances as $creance) {
            if ($creance->getFacture() !== null) {
                throw new AccessDeniedException("La créance " . $creance . " est déjà associée à une facture!");
            }
            $adresse = $creance->getDebiteur()->getSendableAdresse();
            if (!$adresse) {
                throw new \Exception("Debiteur " . $creance->getDebiteur() . " n'a pas d'adresse!");
            }
            if (!isset($factures[$adresse->getId()])) {
                $facture = new Facture();
                $facture->setDebiteur($adresse->getOwner());
                $factures[$adresse->getId()] = $facture;
            }
            $factures[$adresse->getId()]->addCreance($creance);
        }

        return $factures;
    }
}
