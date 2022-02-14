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
        $facture = $this->manager->find('OvescoFacturationBundle:Facture', $id);
        if (!$facture) throw new \Exception("Facture introuvable!");

        foreach($facture->getCreances() as $creance)
            $this->manager->remove($creance);

        $this->manager->remove($facture);
        $this->manager->flush();

        return "Facture supprimée";
    }
}
