<?php

namespace Ovesco\FacturationBundle\Model;

use Ovesco\FacturationBundle\Entity\Facture;

class ParsedBVR
{
    private $factures = [];

    private $notEnough = [];

    private $doublePaiements = [];

    private $alreadyPaid = [];

    private $orphanPaiements = [];

    public function getAlreadyPaid() {
        return $this->alreadyPaid;
    }
    /**
     * @return array
     */
    public function getFactures()
    {
        return $this->factures;
    }

    public function addFacture($facture) {
        $this->factures[] = $facture;
    }

    public function addDoublePaiement($facture) {
        $this->doublePaiements[] = $facture;
    }

    public function addNotEnough($facture) {
        $this->notEnough[] = $facture;
    }

    public function addAlreadyPaid($facture) {
        $this->alreadyPaid[] = $facture;
    }

    /**
     * @return Facture[]
     */
    public function getNotEnough() {
        return $this->notEnough;
    }

    /**
     * @return Facture[]
     */
    public function getOrphanPaiements()
    {
        return $this->orphanPaiements;
    }

    public function addOrphanPaiement($orphanPaiement) {
        $this->orphanPaiements[] = $orphanPaiement;
    }

    /**
     * @return Facture[]
     */
    public function getDoublePaiements()
    {
        return $this->doublePaiements;
    }
}