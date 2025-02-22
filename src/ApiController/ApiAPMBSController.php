<?php

namespace App\ApiController;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Entity\CabaneTimePeriod;
use App\Entity\ReservationLog;
use App\Service\GoogleCalendarManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @Route("/api/v1/public/netBS/apmbs")
 */
class ApiAPMBSController extends AbstractController
{
    /**
     * @param Request $request
     * @return Response
     * @Route("/cabane-metadata/{id}", name="apmbs.api.cabane_metadata")
     */
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
     * @Route("/cabane-reservation/{id}", name="apmbs.api.cabane_reservation")
     */
    public function reservationAction(Request $request, EntityManagerInterface $em, GoogleCalendarManager $gcm, ValidatorInterface $validator, Cabane $cabane) {
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
        $reservation->setDescription($parameters['description']);
        $errors = $validator->validate($reservation);

        if (count($errors) > 0) {
            $errorsString = (string) $errors;
            return new Response($errorsString);
        }

        $em->persist($reservation);
        $em->flush();

        // Update google calendar
        // We currently do NOT write pending reservations to GC
        // $gcm->updateReservation($reservation);
        $gcm->sendEmailToClient($reservation, 'Votre demande de réservation', 'modification');

        return new Response('ok'); 
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/cabane-monthly-events/{id}", name="apmbs.api.cabane_calendar")
     */
    public function monthlyEventsAction(Request $request, Cabane $cabane, EntityManagerInterface $em, GoogleCalendarManager $gcm) {
        $start = new \DateTimeImmutable($request->get('start'));
        $end = $start->modify('+2 months');

        $pendingReservations = $em->createQueryBuilder()->select('r')
            ->from('App:APMBSReservation', 'r')
            ->where('r.start <= :end')
            ->andWhere('r.end >= :start')
            ->andWhere('r.status = :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', APMBSReservation::PENDING)
            ->getQuery()
            ->execute();

        return $this->json(array_merge(
            $gcm->googleEventToJSON($gcm->listReservations($cabane, $start, $start->modify('+1 month'))),
            array_map(function(APMBSReservation $reservation) {
                return $reservation->toJSON();
            }, $pendingReservations)
        ));
    }

    /**
     * @Route("/client/accept/{id}/{email}", name="apmbs.api.client.accept")
     */
    public function clientAcceptReservation(APMBSReservation $reservation, $email, EntityManagerInterface $em, GoogleCalendarManager $gcm) {
        // Denied if not same email or reservation not in pending state
        if ($reservation->getEmail() !== $email || $reservation->getStatus() !== APMBSReservation::MODIFICATION_PENDING) {
            throw $this->createAccessDeniedException("Vous n'avez pas le droit de réaliser cette action.");
        }

        // Update reservation status
        $reservation->setStatus(APMBSReservation::MODIFICATION_ACCEPTED);
        $log = new ReservationLog();
        $log->setUsername($reservation->getEmail());
        $log->setReservation($reservation);
        $log->setPayload(['Modification acceptee par le demandeur']);
        $log->setAction(ReservationLog::MODIFICATION_ACCEPTED);
        $gcm->updateReservation($reservation);
        $em->persist($log);
        $em->flush();

        return $this->render('reservation/client_validate.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    /**
     * @Route("/client/reject/{id}/{email}", name="apmbs.api.client.reject")
     */
    public function clientRejectReservation(APMBSReservation $reservation, $email, EntityManagerInterface $em, GoogleCalendarManager $gcm) {
        // Denied if not same email or reservation not in pending state
        if ($reservation->getEmail() !== $email || $reservation->getStatus() !== APMBSReservation::MODIFICATION_PENDING) {
            throw $this->createAccessDeniedException("Vous n'avez pas le droit de réaliser cette action.");
        }

        // Update reservation status
        $reservation->setStatus(APMBSReservation::CANCELLED);
        $log = new ReservationLog();
        $log->setUsername($reservation->getEmail());
        $log->setReservation($reservation);
        $log->setPayload(['Modification refusee, reservation annulee par le demandeur']);
        $log->setAction(ReservationLog::CANCELLED);
        $gcm->deleteReservation($reservation);
        $em->persist($log);
        $em->flush();

        $gcm->sendEmailToClient($reservation, 'Vous avez annulé votre demande de réservation');
        return $this->render('reservation/client_reject.html.twig', [
            'reservation' => $reservation,
        ]);
    }
}


