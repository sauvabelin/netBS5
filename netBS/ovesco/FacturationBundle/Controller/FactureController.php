<?php

namespace Ovesco\FacturationBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Searcher\SearcherManager;
use NetBS\CoreBundle\Service\PreviewerManager;
use NetBS\CoreBundle\Utils\Modal;
use NetBS\FichierBundle\Entity\BaseFamille;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Exporter\PDFQrFacture;
use Ovesco\FacturationBundle\Form\MassAssignModelType;
use Ovesco\FacturationBundle\Model\MassAssignModel;
use Ovesco\FacturationBundle\Model\QrFactureConfig;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CreanceController
 * @package Ovesco\FacturationBundle\Controller
 */
#[Route('/factures')]
class FactureController extends AbstractController
{
    private $searcherManager;

    public function __construct(SearcherManager $searcherManager)
    {
        $this->searcherManager = $searcherManager;
    }

    #[Route('/aide', name: 'ovesco.facturation.aide')]
    public function aideAction() {
        return $this->render("@OvescoFacturation/facture/aide_facturation.html.twig");
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    #[Route('/attente-paiement', name: 'ovesco.facturation.facture.attente_paiement')]
    public function factureAttentePaiementAction() {
        return $this->render("@OvescoFacturation/facture/attente_paiement.html.twig");
        // return $this->search('Factures en attente de paiement', 'no');
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/attente-impression', name: 'ovesco.facturation.facture.attente_impression')]
    public function factureAttenteImpressionAction() {
        return $this->render("@OvescoFacturation/facture/attente_impression.html.twig");
        // return $this->search('Factures en attente d\'impression', 'yes');
    }

    private function search($title, $printed) {
        $instance       = $this->searcherManager->bind(Facture::class);
        $params         = [];

        if (!$instance->getForm()->isSubmitted()) {
            $params['title'] = $title;
            $form = $instance->getForm();
            $form->get('statut')->submit(Facture::OUVERTE);
            $form->get('isPrinted')->submit($printed);
            $instance->getSearcher()->setForm($form);
        }


        return $this->searcherManager->render($instance, $params);
    }

    #[Route('/search', name: 'ovesco.facturation.search_factures')]
    public function searchFactureAction() {
        $instance = $this->searcherManager->bind(Facture::class);

        if (!$instance->getForm()->isSubmitted()) {
            $form = $instance->getForm();
            $form->get('remarques', 'hochet');
            $form->get('compteToUse')->submit(1);
            $form->get('statut')->submit(Facture::OUVERTE);
            $instance->getSearcher()->setForm($form);
        }

        return $this->searcherManager->render($instance);
    }

    /**
     * @param Facture $facture
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/modal-view/{id}', name: 'ovesco.facturation.facture_modal')]
    public function factureModalAction(Facture $facture) {
        return $this->render('@OvescoFacturation/facture/facture.modal.twig', [
            'facture' => $facture,
        ]);
    }

    /**
     * @param Facture $facture
     * @return \Symfony\Component\HttpFoundation\Response
     */
    #[Route('/modal-pdf-view/{id}', name: 'ovesco.facturation.pdf_facture_modal')]
    public function facturePdfModalAction(Facture $facture) {
        return $this->render('@OvescoFacturation/facture/pdf_facture.modal.twig', [
            'facture' => $facture,
        ]);
    }

    /**
     * @param Facture $facture
     */
    #[Route('/facture-pdf-no-print-date/{id}', name: 'ovesco.facturation.export_pdf_facture_no_date')]
    public function facturePdfNoDateExportAction(Facture $facture, PDFQrFacture $exporter, PreviewerManager $previewerManager) {
        $items      = [$facture];
        $config = new QrFactureConfig();
        $config->setPrintDate = false;
        $exporter->setConfig($config);
        $previewer  = $previewerManager->getPreviewer($exporter->getPreviewer());
        return $previewer->preview($items, $exporter);
    }

    #[Route('/mark-printed', name: 'ovesco.facturation.facture.mark_printed', methods: ['POST'])]
    public function markPrintedAction(Request $request, EntityManagerInterface $em) {
        $this->denyAccessUnlessGranted('update', new Facture());

        $ids = json_decode($request->request->get('ids'), true);
        if (!is_array($ids) || empty($ids)) {
            return new JsonResponse(['error' => 'IDs invalides'], 400);
        }

        $factures = $em->getRepository(Facture::class)->findBy(['id' => $ids]);
        $now = new \DateTime();
        foreach ($factures as $facture) {
            $facture->setDateImpression($now);
        }
        $em->flush();
        return new JsonResponse(['success' => true, 'count' => count($factures)]);
    }

    #[Route('/modal-assign-model', name: 'ovesco.facturation.facture.assign_model_modal')]
    public function assignModelModalAction(Request $request, EntityManagerInterface $em) {
        $mass = new MassAssignModel();
        $mass->setSelectedIds(serialize($request->request->all('selectedIds')));
        $form = $this->createForm(MassAssignModelType::class, $mass);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $selectedIds = unserialize($mass->getSelectedIds());
            $factures = $em->getRepository(Facture::class)->findBy(['id' => $selectedIds]);

            foreach ($factures as $facture) {
                $facture->setFactureModel($mass->getFactureModel());
            }

            $em->flush();
            $this->addFlash("success", count($factures) . " facture(s) mises à jour");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', [
            'form' => $form->createView(),
        ], Modal::renderModal($form));
    }

    #[Route('/resolve-model/{id}', name: 'ovesco.facturation.facture.resolve_model', methods: ['GET'])]
    public function resolveModelAction(Facture $facture, EntityManagerInterface $em) {
        $engine = new ExpressionLanguage();
        $models = $em->getRepository(FactureModel::class)
            ->createQueryBuilder('m')->orderBy('m.poids', 'DESC')->getQuery()->getResult();

        $resolved = null;
        foreach ($models as $model) {
            $rule = $model->getApplicationRule();
            if ($rule === null) {
                $resolved = $model;
                break;
            }
            try {
                $result = $engine->evaluate($rule, [
                    'facture' => $facture,
                    'debiteur' => $facture->getDebiteur(),
                    'isFamille' => $facture->getDebiteur() instanceof BaseFamille,
                ]);
                if ($result) {
                    $resolved = $model;
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return new JsonResponse([
            'model' => $resolved ? $resolved->getName() : 'Aucun modèle applicable',
        ]);
    }

    #[Route('/unmark-printed', name: 'ovesco.facturation.facture.unmark_printed', methods: ['POST'])]
    public function unmarkPrintedAction(Request $request, EntityManagerInterface $em) {
        $this->denyAccessUnlessGranted('update', new Facture());

        $ids = json_decode($request->request->get('ids'), true);
        if (!is_array($ids) || empty($ids)) {
            return new JsonResponse(['error' => 'IDs invalides'], 400);
        }

        $factures = $em->getRepository(Facture::class)->findBy(['id' => $ids]);
        foreach ($factures as $facture) {
            $facture->setDateImpression(null);
        }
        $em->flush();
        return new JsonResponse(['success' => true, 'count' => count($factures)]);
    }
}
