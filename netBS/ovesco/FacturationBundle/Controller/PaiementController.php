<?php

namespace Ovesco\FacturationBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Searcher\SearcherManager;
use NetBS\CoreBundle\Utils\Modal;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Entity\Paiement;
use Ovesco\FacturationBundle\Form\PaiementType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class CompteController
 * @package Ovesco\FacturationBundle\Controller
 * @Route("/paiement")
 */
class PaiementController extends AbstractController
{
    /**
     * @param Facture $facture
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @Route("/{id}/modal-add", name="ovesco.facturation.paiement.modal_add")
     */
    public function modalAddAction(Facture $facture, Request $request, EntityManagerInterface $em) {

        $paiement = new Paiement();
        $form = $this->createForm(PaiementType::class, $paiement);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {

            $em->persist($paiement);
            $facture->addPaiement($paiement);
            $em->flush();
            $this->addFlash('success', 'Paiement ajouté avec succès');
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', array(
            'form'  => $form->createView()
        ), Modal::renderModal($form));
    }

    /**
     * @param Paiement $paiement
     * @Route("/details/{id}", name="ovesco.facturation.paiement.modal_details")
     */
    public function modalDetailsAction(Paiement $paiement) {
        return $this->render('@OvescoFacturation/paiement/modal_details.html.twig', array(
            'paiement' => $paiement,
        ));

    }

    /**
     * @Route("/search", name="ovesco.facturation.search_paiements")
     */
    public function searchPaiementsAction(SearcherManager $searcher) {
        $instance       = $searcher->bind(Paiement::class);
        return $searcher->render($instance);
    }
}
