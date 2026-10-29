<?php

namespace App\ApiController;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Entity\CabaneTimePeriod;
use App\Entity\ReservationLog;
use App\Service\ReservationCalendar;
use App\Service\APMBSClientLinks;
use App\Service\APMBSMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/public/netBS/apmbs')]
class ApiAPMBSController extends AbstractController
{
    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/cabane-metadata/{id}', name: 'apmbs.api.cabane_metadata')]
    public function cabaneMetadataAction(Request $request, Cabane $cabane) {

        $conditions = $cabane->getConditions();
        $resConds = [];
        if ($conditions) {
            $resConds = explode("\n", $conditions);
        }
        return $this->json([
            'nom'                => $cabane->getNom(),
            'availabilityRules'  => $cabane->getAvailabilityRule(),
            'prices'             => $cabane->getPrices(),
            'disabledDates'      => $cabane->getDisabledDates(),
            'conditions'         => $resConds,
            'timePeriods'        => array_map(function(CabaneTimePeriod $timePeriod) {
                return [
                    'name'       => $timePeriod->getNom(),
                    'value'      => $timePeriod->getId(),
                    'hourStart'  => $timePeriod->getTimeStart()->format('H'),
                    'hourEnd'    => $timePeriod->getTimeEnd()->format('H'),
                    'minuteStart'=> $timePeriod->getTimeStart()->format('i'),
                    'minuteEnd'  => $timePeriod->getTimeEnd()->format('i'),
                ];
            }, $cabane->getTimePeriods())
        ]);
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/cabane-reservation/{id}', name: 'apmbs.api.cabane_reservation')]
    public function reservationAction(Request $request, EntityManagerInterface $em, APMBSMailer $mailer, ValidatorInterface $validator, Cabane $cabane) {
        $reservation = new APMBSReservation();
        $reservation->setCabane($cabane);
        $parameters = json_decode($request->getContent(), true);

        $reservation->setStatus(APMBSReservation::PENDING);
        $reservation->setStart(new \DateTime($parameters['start']));
        $reservation->setEnd(new \DateTime($parameters['end']));
        $reservation->setPrenom($parameters['firstname']);
        $reservation->setNom($parameters['lastname']);
        $reservation->setEmail($parameters['email']);
        $reservation->setPhone($parameters['phone']);
        $reservation->setUnite($parameters['unit']);
        $reservation->setRue($parameters['street']);
        $reservation->setNpa($parameters['npa']);
        $reservation->setLocalite($parameters['city']);
        $reservation->setDescription($parameters['description']);
        $errors = $validator->validate($reservation);
        
        if (count($errors) > 0) {
            $errorsString = (string) $errors;
            throw $this->createAccessDeniedException($errorsString);
        }

        $em->persist($reservation);
        $em->flush();

        // Update google calendar
        // We currently do NOT write pending reservations to GC
        // $calendar->updateReservation($reservation);
        $mailer->sendEmailToClient($reservation, 'Votre demande de réservation');

        return new Response('ok'); 
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/cabane-monthly-events/{id}', name: 'apmbs.api.cabane_calendar')]
    public function monthlyEventsAction(Request $request, Cabane $cabane, EntityManagerInterface $em, ReservationCalendar $calendar) {
        $start = new \DateTimeImmutable($request->get('start'));
        $end = new \DateTimeImmutable($request->get('end'));

        $pendingReservations = $em->createQueryBuilder()->select('r')
            ->from(APMBSReservation::class, 'r')
            ->where('r.start <= :end')
            ->andWhere('r.end >= :start')
            ->andWhere('r.status = :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', APMBSReservation::PENDING)
            ->getQuery()
            ->execute();

        $calendarEvents = $calendar->listReservations($cabane, $start, $end);

        return $this->json(array_merge(
            $calendar->googleEventToJSON($calendarEvents),
            array_map(function(APMBSReservation $reservation) {
                return $reservation->toJSON();
            }, $pendingReservations)
        ));
    }

    /**
     * Client's answer to a date change, from the links in the modification
     * email (APMBSClientLinks). GET only shows a confirmation page, so mail
     * link scanners that open the link change nothing. The POST applies it
     * and redirects back here (post/redirect/get, which Turbo also requires),
     * and the GET shows the outcome from the reservation status.
     */
    #[Route('/client/{action}/{id}', name: 'apmbs.api.client.answer', requirements: ['action' => 'accept|reject', 'id' => '\d+'], methods: ['GET', 'POST'])]
    public function clientAnswerAction(Request $request, string $action, APMBSReservation $reservation, APMBSClientLinks $links, EntityManagerInterface $em, ReservationCalendar $calendar, APMBSMailer $mailer) {
        $token = $request->isMethod('POST') ? $request->request->get('token') : $request->query->get('token');
        if (!$links->isValid($reservation, $action, $token)) {
            throw $this->createAccessDeniedException("Ce lien n'est pas valide.");
        }

        if ($reservation->getStatus() !== APMBSReservation::MODIFICATION_PENDING) {
            $step = match (true) {
                $action === 'accept' && $reservation->getStatus() === APMBSReservation::MODIFICATION_ACCEPTED => 'accepted',
                $action === 'reject' && $reservation->getStatus() === APMBSReservation::CANCELLED => 'rejected',
                default => 'already_answered',
            };

            return $this->render('reservation/client_answer.html.twig', ['reservation' => $reservation, 'step' => $step]);
        }

        if (!$request->isMethod('POST')) {
            return $this->render('reservation/client_answer.html.twig', [
                'reservation' => $reservation,
                'step' => 'confirm',
                'action' => $action,
                'token' => $token,
            ]);
        }

        $log = new ReservationLog();
        $log->setUsername($reservation->getEmail());
        $log->setReservation($reservation);
        $reservation->addLog($log);
        $em->persist($log);

        if ($action === 'accept') {
            $reservation->setStatus(APMBSReservation::MODIFICATION_ACCEPTED);
            $log->setPayload(['message' => 'Modification acceptée par le demandeur']);
            $log->setAction(ReservationLog::MODIFICATION_ACCEPTED);
            $calendar->updateReservation($reservation);
        } else {
            $reservation->setStatus(APMBSReservation::CANCELLED);
            $log->setPayload(['message' => 'Modification refusée, réservation annulée par le demandeur']);
            $log->setAction(ReservationLog::CANCELLED);
            $calendar->deleteReservation($reservation);
        }

        $em->flush();

        if ($action === 'reject') {
            $mailer->sendEmailToClient($reservation, 'Vous avez annulé votre demande de réservation');
        }

        return $this->redirect($links->url($reservation, $action), Response::HTTP_SEE_OTHER);
    }

    /**
     * Links from modification emails sent before the signed links existed
     * (they carry the client's email instead of a token). They now only lead
     * to the confirmation page above.
     * TODO: remove once no reservation sent before this change is still
     * waiting for an answer.
     */
    #[Route('/client/{action}/{id}/{email}', name: 'apmbs.api.client.legacy_answer', requirements: ['action' => 'accept|reject', 'id' => '\d+'], methods: ['GET'])]
    public function legacyClientAnswerAction(string $action, APMBSReservation $reservation, string $email, APMBSClientLinks $links) {
        if ($reservation->getEmail() !== $email) {
            throw $this->createAccessDeniedException("Ce lien n'est pas valide.");
        }

        return $this->redirect($links->url($reservation, $action));
    }
}


