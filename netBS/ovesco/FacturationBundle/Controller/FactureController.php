<?php

namespace Ovesco\FacturationBundle\Controller;

use NetBS\CoreBundle\Searcher\SearcherManager;
use NetBS\CoreBundle\Service\PreviewerManager;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Exporter\PDFQrFacture;
use Ovesco\FacturationBundle\Model\FactureConfig;
use Ovesco\FacturationBundle\Model\QrFactureConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CreanceController
 * @package Ovesco\FacturationBundle\Controller
 * @Route("/factures")
 */
class FactureController extends AbstractController
{
    private $searcherManager;

    public function __construct(SearcherManager $searcherManager)
    {
        $this->searcherManager = $searcherManager;
    }

    /**
     * @Route("/attente-paiement", name="ovesco.facturation.facture.attente_paiement")
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function factureAttentePaiementAction() {
        return $this->search('Factures en attente de paiement', 'no');
    }

    /**
     * @Route("/attente-impression", name="ovesco.facturation.facture.attente_impression")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function factureAttenteImpressionAction() {
        return $this->search('Factures en attente d\'impression', 'yes');
    }

    private function search($title, $printed) {
        $instance       = $this->searcherManager->bind(Facture::class);
        $params         = [];

        if (!$instance->getForm()->isSubmitted()) {
            $params['title'] = $title;
            $instance->getForm()->get('statut')->submit(Facture::OUVERTE);
            $instance->getForm()->get('isPrinted')->submit($printed);
            $instance->getSearcher()->setForm($instance->getForm());
        }


        return $this->searcherManager->render($instance, $params);
    }

    /**
     * @Route("/search", name="ovesco.facturation.search_factures")
     */
    public function searchFactureAction() {
        $instance = $this->searcherManager->bind(Facture::class);
        return $this->searcherManager->render($instance);
    }

    /**
     * @param Facture $facture
     * @Route("/modal-view/{id}", name="ovesco.facturation.facture_modal")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function factureModalAction(Facture $facture) {
        return $this->render('@OvescoFacturation/facture/facture.modal.twig', [
            'facture' => $facture,
        ]);
    }

    /**
     * @param Facture $facture
     * @Route("/modal-pdf-view/{id}", name="ovesco.facturation.pdf_facture_modal")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function facturePdfModalAction(Facture $facture) {
        return $this->render('@OvescoFacturation/facture/pdf_facture.modal.twig', [
            'facture' => $facture,
        ]);
    }

    /**
     * @param Facture $facture
     * @Route("/facture-pdf-no-print-date/{id}", name="ovesco.facturation.export_pdf_facture_no_date")
     */
    public function facturePdfNoDateExportAction(Facture $facture, PDFQrFacture $exporter, PreviewerManager $previewerManager) {
        $items      = [$facture];
        $config = new QrFactureConfig();
        $exporter->setConfig($config);
        $previewer  = $previewerManager->getPreviewer($exporter->getPreviewer());
        return $previewer->preview($items, $exporter);
    }
}
