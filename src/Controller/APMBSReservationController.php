<?php

namespace App\Controller;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Service\GoogleCalendarManager;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Form\Type\DatepickerType;
use NetBS\CoreBundle\Utils\Modal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class APMBSReservationController extends AbstractController
{
    /**
     * @param Request $request
     * @Route("/apmbs/reservations/dashboard", name="sauvabelin.apmbs_reservations.dashboard")
     * @return Response
     */
    public function apmbsReservations(Request $request, EntityManagerInterface $em) {

        /*
        $r = new APMBSReservation();
        $r->setCabane($em->getRepository('App:Cabane')->find(1));
        $r->setDescription("Camp de troupe");
        $r->setEmail("guillaume.hochet@gmail.com");
        $r->setNom("Hochet");
        $r->setPrenom("Guillaume");
        $r->setStart(\DateTime::createFromFormat("d-m-Y", "01-06-2022"));
        $r->setEnd(\DateTime::createFromFormat("d-m-Y", "02-06-2022"));
        $r->setUnite("Montfort");
        $r->setPhone("0774117718");

        $em->persist($r);
        $em->flush();
        */

        return $this->render('reservation/dashboad.html.twig', [
            'cabanes' => $em->getRepository('App:Cabane')->findAll(),
            'googleCalendarApiKey' => $_ENV['GOOGLE_CALENDAR_API_KEY'],
        ]);
    }

    /**
     * @param Request $request
     * @Route("/apmbs/reservations/pending-and-refused/{id}", name="sauvabelin.apmbs_reservations.get_pending_and_refused")
     * @return Response
     */
    public function getPendingAndRefusedReservations(Request $request, Cabane $cabane, EntityManagerInterface $em) {
        $startDate = \DateTime::createFromFormat(\DateTime::ISO8601, $request->request->get('start'));
        $endDate = \DateTime::createFromFormat(\DateTime::ISO8601, $request->request->get('end'));

        $query = $em->getRepository('App:APMBSReservation')->createQueryBuilder('r');
        $reservations = $query->where('r.cabane = :c')
            ->setParameter('c', $cabane)
            ->andWhere($query->expr()->orX('r.status = :pending', 'r.status = :refused'))
            ->setParameter('pending', APMBSReservation::PENDING)
            ->setParameter('refused', APMBSReservation::REFUSED)
            ->andWhere($query->expr()->orX(
                'r.start <= :end',
                'r.end >= :start'
            ))
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate);

        $results = $reservations->getQuery()->getResult();
        $response = [];
        /** @var APMBSReservation $event */
        foreach ($results as $event) {
            $response[] = [
                'id' => $event->getId(),
                'start' => $event->getStart()->format(\DateTime::ISO8601),
                'end' => $event->getEnd()->format(\DateTime::ISO8601),
                'title' => $event->getUnite() . " [" . $event->getStatus() . "]",
            ];
        }

        return new JsonResponse($response);
    }

    /**
     * @param Request $request
     * @Route("/apmbs/reservations/modal", name="sauvabelin.apmbs_reservations.modal")
     * @return Response
     */
    public function editReservationModal(Request $request, EntityManagerInterface $em) {
        
        $id = $request->get('id');
        if (!$id) {
            throw $this->createNotFoundException("Pas d'identifiant donné");
        }

        // We first try to retrieve the reservation with the given id
        $reservation = $em->getRepository('App:APMBSReservation')->find($id);

        // If not found, try to get it from googleEventId
        if (!$reservation) {
            $reservation = $em->getRepository('App:APMBSReservation')->findOneBy(['gcEventId' => $id]);
            if (!$reservation) {
                // Unable to find it, notify user, sad story
                throw $this->createNotFoundException("La réservation est introuvable, elle est probablement présente sur le calendrier Google mais pas dans le système");
            }
        }

        return $this->render('reservation/view_reservation_modal.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    /**
     * @param Request $request
     * @Route("/apmbs/reservations/accept/{id}", name="sauvabelin.apmbs_reservations.accept")
     * @return Response
     */
    public function acceptAction(Request $request, GoogleCalendarManager $gcm, EntityManagerInterface $em, APMBSReservation $reservation) {

        $form = $this->createFormBuilder(['message' => ''])
            ->add('message', TextareaType::class, ['label' => 'Message additionnel', 'required' => false])
            ->getForm();


        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message = $form->getData()['message'];
            // Send email and add to google calendar
            $eventId = $gcm->updateReservation($reservation);
            $reservation->setGCEventId($eventId);
            $reservation->setStatus(APMBSReservation::ACCEPTED);
            $em->persist($reservation);
            $em->flush();

            $this->addFlash('success', 'Réservation approuvée');
            return $this->redirectToRoute('sauvabelin.apmbs_reservations.dashboard');
        }
        
        return $this->render('reservation/accept_reservation.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Request $request
     * @Route("/apmbs/reservations/reject/{id}", name="sauvabelin.apmbs_reservations.reject")
     * @return Response
     */
    public function rejectAction(Request $request, GoogleCalendarManager $gcm, EntityManagerInterface $em, APMBSReservation $reservation) {

        $form = $this->createFormBuilder(['message' => ''])
            ->add('message', TextareaType::class, ['label' => 'Message additionnel', 'required' => false])
            ->getForm();


        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message = $form->getData()['message'];
            $gcm->removeReservation($reservation);
            $reservation->setRefusedMotif($message);
            $reservation->setGCEventId(null);
            $reservation->setStatus(APMBSReservation::REFUSED);
            $em->persist($reservation);
            $em->flush();

            $this->addFlash('info', 'Réservation refusée');
            return $this->redirectToRoute('sauvabelin.apmbs_reservations.dashboard');
        }
        
        return $this->render('reservation/reject_reservation.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }

    /**
     * @param Request $request
     * @Route("/apmbs/reservations/update/{id}", name="sauvabelin.apmbs_reservations.update")
     * @return Response
     */
    public function updateAction(Request $request, GoogleCalendarManager $gcm, EntityManagerInterface $em, APMBSReservation $reservation) {

        $form = $this->createFormBuilder([
                'message' => '',
                'start' => $reservation->getStart(),
                'end' => $reservation->getEnd(),
            ])
            ->add('message', TextareaType::class, ['label' => 'Message additionnel', 'required' => false])
            ->add('start', DateTimeType::class, ['label' => 'Début'])
            ->add('end', DateTimeType::class, ['label' => 'fin'])
            ->getForm();


        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $reservation->setStart($data['start']);
            $reservation->setEnd($data['end']);
            
            // Send email and add to google calendar
            $eventId = $gcm->updateReservation($reservation);
            $reservation->setGCEventId($eventId);
            $reservation->setStatus(APMBSReservation::ACCEPTED);
            $em->persist($reservation);
            $em->flush();

            $this->addFlash('success', 'Réservation approuvée');
            return $this->redirectToRoute('sauvabelin.apmbs_reservations.dashboard');
        }
        
        return $this->render('reservation/modify_reservation.html.twig', [
            'reservation' => $reservation,
            'form' => $form->createView(),
        ]);
    }
}


