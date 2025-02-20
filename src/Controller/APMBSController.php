<?php

namespace App\Controller;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Entity\CabaneTimePeriod;
use App\Entity\Intendant;
use App\Entity\ReservationLog;
use App\Form\APMBSReservationType;
use App\Form\CabaneTimePeriodType;
use App\Form\CabaneType;
use App\Form\IntendantType;
use App\Form\ModifyReservationType;
use App\Form\ReservationAcceptType;
use App\Form\ReservationMessageType;
use App\Model\AcceptReservation;
use App\Model\ModifyReservation;
use App\Model\ReservationMessage;
use App\Service\GoogleCalendarManager;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Utils\Modal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * @Route("/apmbs")
 * @Security("is_granted('ROLE_APMBS')")
 */
class APMBSController extends AbstractController
{
    /**
     * @Route("/cabanes/{id}", name="sauvabelin.apmbs.cabane")
     */
    public function cabaneAction(Cabane $cabane) {
        return $this->render('cabane/cabane_page.html.twig', [
            'cabane' => $cabane,
            'cabaneForm' => $this->createForm(CabaneType::class, $cabane)->createView(),
        ]);
    }

    private function getHighlightColor(APMBSReservation $reservation) {
        $bgColor = 'darkblue';
        if ($reservation->getStatus() === APMBSReservation::PENDING) {
            $bgColor = 'darkorange';
        } else if ($reservation->getStatus() === APMBSReservation::ACCEPTED) {
            $bgColor = 'darkgreen';
        } else if ($reservation->getStatus() === APMBSReservation::REFUSED) {
            $bgColor = 'darkred';
        } else if ($reservation->getStatus() === APMBSReservation::CANCELLED) {
            $bgColor = 'darkgrey';
        } else if ($reservation->getStatus() === APMBSReservation::MODIFICATION_PENDING) {
            $bgColor = 'darkorange';
        } else if ($reservation->getStatus() === APMBSReservation::MODIFICATION_ACCEPTED) {
            $bgColor = 'darkgreen';
        }

        return $bgColor;
    }

    /**
     * @Route("/cabane/{id}/full-calendar-reservations", name="sauvabelin.apmbs.full_calendar_cabane_reservations")
     */
    public function cabaneFullCalendarReservationsAction(Cabane $cabane, Request $request, EntityManagerInterface $em, GoogleCalendarManager $gcm) {
        $start = new \DateTimeImmutable($request->get('start'));
        $end = new \DateTimeImmutable($request->get('end'));
        $highlight = $request->get('reservationId');
        $highlight = $highlight ? intval($highlight) : null;

        // No need to take larger range for google calendar
        $gcmReservations = $gcm->listReservations($cabane, $start, $end);
        $result = [];
        $foundHighlight = false;

        // Get all reservations matching google ones
        /** @var APMBSReservation[] $mappedReservations */
        $mappedReservations = $em->createQueryBuilder()
            ->select('r')
            ->from(APMBSReservation::class, 'r')
            ->where('r.gcEventId IN (:eventIds)')
            ->setParameter('eventIds', array_map(function($event) { return $event->getId(); }, $gcmReservations))
            ->getQuery()
            ->getResult();

        /** @var \Google\Service\Calendar\Event $gcmReservation */
        foreach ($gcmReservations as $gcmReservation) {
            $foundItem = null;
            foreach ($mappedReservations as $rrr) {
                if ($rrr->getGCEventId() === $gcmReservation->getId()) {
                    $foundItem = $rrr;
                    break;
                }
            }

            $bgColor = $highlight ? 'aliceblue' : null;
            $textColor = $highlight ? 'black' : 'white';
            $url = $gcmReservation->htmlLink;
            if ($foundItem) {
                $textColor = 'white';
                $bgColor = 'darkblue';
                $url = $this->generateUrl('sauvabelin.apmbs.reservation', ['id' => $foundItem->getId()]);
                if ("{$foundItem->getId()}" === "$highlight") {
                    $foundHighlight = true;
                    $bgColor = $this->getHighlightColor($foundItem);
                }
            }

            $result[] = [
                'start' => (new \DateTimeImmutable($gcmReservation->start->dateTime ? $gcmReservation->start->dateTime : $gcmReservation->start->date))->format('c'),
                'end'   => (new \DateTimeImmutable($gcmReservation->end->dateTime ? $gcmReservation->end->dateTime : $gcmReservation->end->date))->format('c'),
                'title'   => $gcmReservation->summary,
                'backgroundColor' => $bgColor,
                'textColor' => $textColor,
                'url' => $url,
            ];
        }



        $qb = $em->createQueryBuilder();
        $reservations = $qb
            ->select('r')
            ->from(APMBSReservation::class, 'r')
            ->where('r.cabane = :cabane')
            ->andWhere('r.start >= :start')
            ->andWhere('r.end <= :end')
            ->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->eq('r.status', "'" . APMBSReservation::PENDING . "'"),
                    $qb->expr()->eq('r.id', ':highlight')
                )
            )
            ->setParameter('cabane', $cabane)

            // Take larger range to account for reservations that start before the range
            ->setParameter('start', $start->modify('-1 month'))
            ->setParameter('end', $end->modify('+1 month'))
            ->setParameter('highlight', $highlight)
            ->getQuery()
            ->getResult();

        /** @var APMBSReservation $reservation */
        foreach ($reservations as $reservation) {

            $bgColor = $highlight ? 'bisque' : "darkorange";
            if ("{$reservation->getId()}" === "$highlight") {
                if ($foundHighlight) {

                    // Already coming from google calendar events
                    continue;
                }
                $bgColor = $this->getHighlightColor($reservation);
            }

            $result[] = [
                'start' => $reservation->getStart()->format('c'),
                'end'   => $reservation->getEnd()->format('c'),
                'title'   => $reservation->getTitle(),
                'backgroundColor' => $bgColor,
                'textColor' => $highlight ? 'black' : 'white',
                'url' => $this->generateUrl('sauvabelin.apmbs.reservation', ['id' => $reservation->getId()])
            ];
        }

        return $this->json($result);
    }

    /**
     * @Route("/cabane/edit/{id}", name="sauvabelin.apmbs.cabane_edit")
     */
    public function cabaneEditAction(Cabane $cabane, Request $request, EntityManagerInterface $em) {
        $form = $this->createForm(CabaneType::class, $cabane);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($cabane);
            $em->flush();
            $this->addFlash('success', 'Cabane enregistrée avec succès');
            return $this->redirectToRoute('sauvabelin.apmbs.cabane', ['id' => $cabane->getId()]);
        }
        return $this->render('cabane/cabane_form.html.twig', [
            'cabane' => $cabane,
            'cabaneForm' => $form->createView(),
        ]);
    }

    /**
     * @Route("/add-cabane", name="sauvabelin.apmbs.add_cabane")
     */
    public function addCabaneAction(Request $request, EntityManagerInterface $em) {
        $cabane = new Cabane();
        $form = $this->createForm(CabaneType::class, $cabane);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($cabane);
            $em->flush();
            $this->addFlash('success', 'Cabane enregistrée avec succès');
            return $this->redirectToRoute('sauvabelin.apmbs.cabane', ['id' => $cabane->getId()]);
        }

        return $this->render('@NetBSCore/generic/form.generic.twig', array(
            'header'    => 'Nouvelle Cabane',
            'subHeader' => "Enregistrer une nouvelle cabane dans le système",
            'form'  => $form->createView()
        ));
    }

    /**
     * @Route("/reservations", name="sauvabelin.apmbs.reservations")
     */
    public function reservationsAction() {
        return $this->render("@NetBSCore/generic/list.generic.twig", [
            "header" => "Réservations",
            "subHeader" => "Liste des réservations de cabanes",
            "list" => "app.apmbs.reservations",
        ]);
    }

    /**
     * @Route("/reservation/{id}", name="sauvabelin.apmbs.reservation")
     */
    public function viewReservationAction(APMBSReservation $reservation, GoogleCalendarManager $gcm) {
        $form = $this->createForm(APMBSReservationType::class, $reservation);
        $eventualConflicts = $gcm->listReservations($reservation->getCabane(), $reservation->getStart(), $reservation->getEnd());
        return $this->render('reservation/view.html.twig', [
            'reservation' => $reservation,
            'conflicts' => $eventualConflicts,
            'reservationForm' => $form->createView(),
        ]);
    }


    /**
     * @Route("/time-periods", name="sauvabelin.apmbs.time_periods")
     */
    public function timePeriodAction(RouterInterface $router) {

        return $this->render('@NetBSFichier/generic/page_generic.html.twig', array(
            'list'      => 'app.cabane_time_period',
            'title'     => "Périodes de réservation définies",
            'subtitle'  => "Permet de définir des périodes de la journée réservables",
            'modalPath' => $router->generate('sauvabelin.apmbs.time_periods.modal_add')
        ));
    }

    /**
     * @param Request $request
     * @Route("/time-period/modal/add", name="sauvabelin.apmbs.time_periods.modal_add")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addTimePeriodModalAction(Request $request, EntityManagerInterface $em) {
        $timePeriod = new CabaneTimePeriod();
        $form = $this->createForm(CabaneTimePeriodType::class, $timePeriod);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $em->persist($form->getData());
            $em->flush();

            $this->addFlash('success', "Période enregistré");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', [
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }

    /**
     * @Route("/intendants", name="sauvabelin.apmbs.intendants")
     */
    public function intendantsAction(RouterInterface $router) {

        return $this->render('@NetBSFichier/generic/page_generic.html.twig', array(
            'list'      => 'app.intendants',
            'title'     => "Intendants",
            'subtitle'  => "Les intendants de l'APMBS",
            'modalPath' => $router->generate('sauvabelin.apmbs.intendants.modal_add')
        ));
    }

    /**
     * @param Request $request
     * @Route("/intendant/modal/add", name="sauvabelin.apmbs.intendants.modal_add")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addIntendantModalAction(Request $request, EntityManagerInterface $em) {
        $intendant = new Intendant();
        $form = $this->createForm(IntendantType::class, $intendant);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $em->persist($form->getData());
            $em->flush();

            $this->addFlash('success', "Intendant enregistré");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', [
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }
    
    /**
     * @Route("/reservation/{id}/modify", name="sauvabelin.apmbs.reservation.modify")
     */
    public function reservationModifyModalAction(Request $request, APMBSReservation $reservation, EntityManagerInterface $em, GoogleCalendarManager $gcm) {
        $modify = new ModifyReservation();
        $modify->start = $reservation->getStart();
        $modify->end = $reservation->getEnd();

        $form = $this->createForm(ModifyReservationType::class, $modify);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $log = new ReservationLog();
            $log->setUsername($this->getUser()->getUserIdentifier());
            $log->setReservation($reservation);

            $oldStart = $reservation->getStart();
            $oldEnd = $reservation->getEnd();
            $reservation->setStart($modify->start);
            $reservation->setEnd($modify->end);

            $log->setPayload([
                'message' => $modify->message,
                'oldStart' => $oldStart->format('d.m.Y H:i'),
                'oldEnd' => $oldEnd->format('d.m.Y H:i'),
                'newStart' => $reservation->getStart()->format('d.m.Y H:i'),
                'newEnd' => $reservation->getEnd()->format('d.m.Y H:i')
            ]);

            
            $gcm->deleteReservation($reservation);
            $reservation->setStatus(APMBSReservation::MODIFICATION_PENDING);
            $em->persist($reservation);
            $log->setAction(ReservationLog::MODIFY);
            $em->persist($log);
            $em->flush();

            $gcm->sendEmailToClient($reservation, 'Demande de réservation en attente', $modify->message, 'modification', [
                'oldStart' => $oldStart,
                'oldEnd' => $oldEnd,
            ]);
            $this->addFlash('success', "Notification de modification envoyée");
            return Modal::refresh();
        }

        return $this->render('reservation/modify.modal.twig', [
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }

    /**
     * @Route("/reservation/{id}/accept", name="sauvabelin.apmbs.reservation.accept")
     */
    public function reservationAcceptModalAction(Request $request, APMBSReservation $reservation, EntityManagerInterface $em, GoogleCalendarManager $gcm) {
        $msg = new AcceptReservation();
        $form = $this->createForm(ReservationAcceptType::class, $msg, ['cabane' => $reservation->getCabane()]);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $log = new ReservationLog();
            $log->setUsername($this->getUser()->getUserIdentifier());
            $log->setReservation($reservation);
            $log->setPayload(['message' => $msg->message]);
            $log->setAction(ReservationLog::ACCEPTED);

            $reservation->setStatus(APMBSReservation::ACCEPTED);
            $reservation->setIntendantDebut($msg->intendantDebut);
            $reservation->setIntendantFin($msg->intendantFin);

            $gcm->updateReservation($reservation);
            $em->persist($reservation);
            $em->persist($log);
            $em->flush();

            $gcm->sendEmailToClient($reservation, 'Réservation validée', $msg->message);
            $this->addFlash('success', "Réservation validée");
            return Modal::refresh();
        }

        return $this->render('reservation/message.modal.twig', [
            'title' => 'Valider',
            'type' => 'success',
            'alert' => "Vous allez valider cette réservation, ce qui entraînera son inscription dans l'agenda de la cabane et enverra un e-mail de confirmation au demandeur",
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }

    /**
     * @Route("/reservation/{id}/refuse", name="sauvabelin.apmbs.reservation.refuse")
     */
    public function reservationRefuseModalAction(Request $request, APMBSReservation $reservation, EntityManagerInterface $em, GoogleCalendarManager $gcm) {
        $msg = new ReservationMessage();
        $form = $this->createForm(ReservationMessageType::class, $msg);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $log = new ReservationLog();
            $log->setUsername($this->getUser()->getUserIdentifier());
            $log->setReservation($reservation);
            $log->setPayload(['message' => $msg->message]);
            $log->setAction(ReservationLog::REFUSED);

            $reservation->setStatus(APMBSReservation::REFUSED);
            $gcm->deleteReservation($reservation);
            $em->persist($reservation);
            $em->persist($log);
            $em->flush();
            
            $gcm->sendEmailToClient($reservation, 'Demande de réservation refusée', $msg->message);
            $this->addFlash('info', "Réservation refusée");
            return Modal::refresh();
        }

        return $this->render('reservation/message.modal.twig', [
            'title' => 'Refuser',
            'type' => 'info',
            'alert' => "Vous allez valider refuser réservation ce qui enverra un e-mail d'information au demandeur",
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }

    /**
     * @Route("/reservation/{id}/cancel", name="sauvabelin.apmbs.reservation.cancel")
     */
    public function reservationCancelModalAction(Request $request, APMBSReservation $reservation, EntityManagerInterface $em, GoogleCalendarManager $gcm) {
        $msg = new ReservationMessage();
        $form = $this->createForm(ReservationMessageType::class, $msg);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $log = new ReservationLog();
            $log->setUsername($this->getUser()->getUserIdentifier());
            $log->setReservation($reservation);
            $log->setPayload(['message' => $msg->message]);
            $log->setAction(ReservationLog::CANCELLED);

            $reservation->setStatus(APMBSReservation::CANCELLED);
            $gcm->deleteReservation($reservation);
            $em->persist($reservation);
            $em->persist($log);
            $em->flush();

            $gcm->sendEmailToClient($reservation, 'Demande de réservation annulée', $msg->message);
            $this->addFlash('info', "Réservation annulée");
            return Modal::refresh();
        }

        return $this->render('reservation/message.modal.twig', [
            'title' => 'Refuser',
            'type' => 'info',
            'alert' => "Vous allez annuler cette réservation, ce qui enverra un e-mail d'information au demandeur. L'annulation a lieu si le demandeur s'est trompé ou s'est rétracté",
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }
}
