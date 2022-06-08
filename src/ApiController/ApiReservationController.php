<?php

namespace App\ApiController;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Service\GoogleCalendarManager;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\Calendar\Event;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ApiReservationController extends AbstractController
{
    /**
     * @param Request $request
     * @return Response
     * @Route("/api/v1/public/netBS/bs/apmbs/reservation", name="sauvabelin.apmbs.reservation_endpoint")
     */
    public function publicAccessAction(Request $request, EntityManagerInterface $em) {

        $data = json_decode($request->getContent(), true);
        $cabaneId = $data['cabane'];

        $cabane = $em->getRepository('App:Cabane')->find($cabaneId);
        if (!$cabane) {
            throw new \Exception("Unknown cabane");
        }

        $reservation = new APMBSReservation();
        $reservation->setCabane($cabane);
        $reservation->setStatus(APMBSReservation::PENDING);
        $reservation->setUnite($data['groupe']);
        $reservation->setPhone($data['telephone']);
        $reservation->setNom($data['nom']);
        $reservation->setPrenom($data['prenom']);
        $reservation->setStart(\DateTime::createFromFormat('Y-m-d\TH:i:s+', $data['start']));
        $reservation->setEnd(\DateTime::createFromFormat('Y-m-d\TH:i:s+', $data['end']));
        $reservation->setEmail($data['email']);
        $reservation->setDescription($data['activite']);

        $em->persist($reservation);
        $em->flush();
        return new Response();
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/api/v1/public/netBS/bs/apmbs/availability", name="sauvabelin.apmbs.availability_endpoint")
     */
    public function getCabaneAvailability(Request $request, EntityManagerInterface $em, GoogleCalendarManager $gcm) {
        $data = json_decode($request->getContent(), true);
        $cabaneId = $data['cabaneId'];
        $start = \DateTime::createFromFormat('Y-m-d\TH:i:s+', $data['start']);
        $end = \DateTime::createFromFormat('Y-m-d\TH:i:s+', $data['end']);

        $cabane = $em->find('App:Cabane', $cabaneId);

        if (!$cabane || !$start || !$end) {
            throw $this->createAccessDeniedException();
        }

        // Get all events from google for corresponding interval
        $res = $gcm->getEventsBetween($cabane, $start, $end);

        // Simply return the occupancy of the cabane
        return new JsonResponse(array_map(fn(Event $event) => [
            'start' => $event->getStart()->dateTime,
            'end' => $event->getEnd()->dateTime,
        ], $res));
    }
}


