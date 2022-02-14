<?php

namespace Ovesco\FacturationBundle\Controller;

use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\ListModel\FacturesAttenteImpressionList;
use Ovesco\FacturationBundle\ListModel\FacturesAttentePaiementList;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class FacturationController extends AbstractController
{
    /**
     * @Route("/dashboard", name="ovesco.facturation.dashboard")
     */
    public function dashboardAction(FacturesAttentePaiementList $attentePaiementList, FacturesAttenteImpressionList $attenteImpressionList) {

        $attentePaiement = $attentePaiementList->getElements();
        $attenteImpression = $attenteImpressionList->getElements();
        return $this->render('@OvescoFacturation/dashboard.html.twig', [
            'attentePaiement' => count($attentePaiement),
            'amountPaiement' => $this->amountThunes($attentePaiement),
            'attenteImpression' => count($attenteImpression),
            'amountImpression' => $this->amountThunes($attenteImpression),
        ]);
    }

    /**
     * @param Facture[] $factures
     * @return mixed
     */
    private function amountThunes($factures) {
        return array_reduce($factures, function($carry, Facture $facture) {
            return $carry + $facture->getMontantEncoreDu();
        }, 0);
    }
}
