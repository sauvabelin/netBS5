<?php

namespace App\Controller;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Form\CabaneType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CabaneController extends AbstractController
{
    /**
     * @param Request $request
     * @Route("/apmbs/cabanes/dashboard", name="sauvabelin.cabanes.dashboard")
     * @return Response
     */
    public function apmbsCabanes(Request $request, EntityManagerInterface $em) {
        return $this->render('cabane/cabane_dashboard.html.twig', [
            'cabanes' => $em->getRepository('App:Cabane')->findAll(),
        ]);
    }

    /**
     * @param Request $request
     * @Route("/apmbs/cabanes/view/{id}", name="sauvabelin.cabanes.view")
     * @return Response
     */
    public function apmbsCabane(Cabane $cabane, Request $request, EntityManagerInterface $em) {
        dump($cabane);
    }

    /**
     * @param Request $request
     * @Route("/apmbs/cabanes/edit/{id}", defaults={"id"=null}, name="sauvabelin.cabanes.edit")
     * @return Response
     */
    public function editCabane($id, Request $request, EntityManagerInterface $em) {
        $cabane = $id ? $em->find(Cabane::class, $id) : new Cabane();
        $form = $this->createForm(CabaneType::class, $cabane);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $em->persist($form->getData());
            $em->flush();
            $this->addFlash('success', 'Cabane enregistrée');
            return $this->redirectToRoute('sauvabelin.cabanes.view', ['id' => $cabane->getId()]);
        }

        return $this->render('cabane/cabane_edit.html.twig', [
            'cabane' => $cabane,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Request $request
     * @Route("/apmbs/cabanes/email-check", name="sauvabelin.cabanes.email_check")
     * @return Response
     */
    public function emailCheck(Request $request, EntityManagerInterface $em) {
        $type = $request->get('type');
        $id = $request->get('id');

        if (!$id) {
            return new Response("La prévisualisation est disponible uniquement lorsque la cabane est enregistrée, pendant édition");
        }

        $cabane = $em->find(Cabane::class, $id);
        $fake = new APMBSReservation();
        $fake->setCabane($cabane);
        $fake->setDescription("Cours de vol avec le vif d'or");
        $fake->setEmail("lucius@malfoy.yo");
        $fake->setStart(new \DateTime());
        $fake->setEnd(new \DateTime());
        $fake->setNom("Malfoy");
        $fake->setPrenom("Lucius");
        $fake->setPhone("012 345 67 89");
        $fake->setUnite("Les nimbus");
        $fake->setStatus(APMBSReservation::PENDING);
        return $this->render('reservation/email_layout.html.twig', [
            'reservation' => $fake,
            'type' => $type,
        ]);
    }
}


