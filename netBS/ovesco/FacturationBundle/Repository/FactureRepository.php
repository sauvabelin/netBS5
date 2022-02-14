<?php

namespace Ovesco\FacturationBundle\Repository;


use Doctrine\ORM\EntityRepository;
use Ovesco\FacturationBundle\Entity\Facture;

class FactureRepository extends EntityRepository
{
    /**
     * @param $id
     * @return null|Facture
     */
    public function findByFactureId($id) {

        $oldFichierFacture  = $this->findOneBy(array('oldFichierId' => $id));
        return $oldFichierFacture ? $oldFichierFacture : $this->find($id);
    }
}