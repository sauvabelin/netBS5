<?php

namespace Ovesco\FacturationBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Utils\Modal;
use Ovesco\FacturationBundle\Entity\Rappel;
use Ovesco\FacturationBundle\Form\MassRappelType;
use Ovesco\FacturationBundle\Model\MassRappel;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class RappelController
 * @package Ovesco\FacturationBundle\Controller
 * @Route("/rappel")
 */
class RappelController extends AbstractController
{
    /**
     * @param Request $request
     * @Route("/modal-add", name="ovesco.facturation.rappel.add_modal")
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function addModalAction(Request $request, EntityManagerInterface $em) {

        $mass = new MassRappel();
        $mass->setSelectedIds(serialize($request->request->get('selectedIds')));
        $form = $this->createForm(MassRappelType::class, $mass);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $selectedIds    = unserialize($mass->getSelectedIds());

            foreach($selectedIds as $selectedId) {

                $facture = $em->find('OvescoFacturationBundle:Facture', $selectedId);
                if (!$facture) throw new \Exception("Facture $selectedId introuvable!");
                $rappel = new Rappel();
                $rappel->setDate($mass->getDate());
                $facture->addRappel($rappel);
                $em->persist($rappel);
            }

            $em->flush();
            $this->addFlash("success", count($selectedIds) . " rappels ajoutés");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', [
            'form'      => $form->createView(),
        ], Modal::renderModal($form));

    }
}
