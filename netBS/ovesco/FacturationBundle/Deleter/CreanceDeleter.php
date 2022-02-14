<?php

namespace Ovesco\FacturationBundle\Deleter;

use NetBS\CoreBundle\Model\BaseDeleter;
use Ovesco\FacturationBundle\Entity\Creance;
use Ovesco\FacturationBundle\Entity\Facture;

class CreanceDeleter extends BaseDeleter
{
    public function getManagedClass()
    {
        return Creance::class;
    }

    public function remove($id)
    {
        $rmf = false;
        $creance = $this->manager->find('OvescoFacturationBundle:Creance', $id);
        if (!$creance) throw new \Exception("Creance introuvable!");
        if ($creance->getFacture()) {
            $facture = $creance->getFacture();
            if ($facture->getLatestImpression() || $facture->getStatut() !== Facture::OUVERTE)
                throw new \Exception("Vous tentez de supprimer une créance d'une facture non ouverte et/ou déjà imprimée!");

            $facture->removeCreance($creance);
            if (count($facture->getCreances()) === 0) {
                $this->manager->remove($facture);
                $rmf = true;
            }
        }

        $this->manager->remove($creance);
        $this->manager->flush();

        return $rmf ? "Créance supprimée, la facture était du coup vide elle a été supprimée également" : "Créance supprimée";
    }
}
