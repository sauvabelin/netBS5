<?php

namespace Ovesco\FacturationBundle\Deleter;

use NetBS\CoreBundle\Model\BaseDeleter;
use Ovesco\FacturationBundle\Entity\Facture;

class FactureDeleter extends BaseDeleter
{
    public function getManagedClass()
    {
        return Facture::class;
    }

    public function remove($id)
    {
        $facture = $this->manager->find(Facture::class, $id);
        if (!$facture) throw new \Exception("Facture introuvable!");
        if ($facture->getLatestImpression() || $facture->getStatut() !== Facture::OUVERTE || count($facture->getPaiements()) > 0)
            throw new \Exception("Seule une facture ouverte, jamais imprimée et sans paiement peut être supprimée. Annulez-la plutôt.");

        foreach($facture->getCreances() as $creance)
            $this->manager->remove($creance);

        $this->manager->remove($facture);
        $this->manager->flush();

        return "Facture supprimée";
    }
}
