<?php

namespace App\Service;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\Calendar\Event;
use Google_Client;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class GoogleCalendarManager {

    /**
     * @var \Google\Service\Calendar
     */
    private $service;

    /**
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * @var MailerInterface
     */
    private $mailer;

    public function __construct($serviceAccountJson, EntityManagerInterface $em, MailerInterface $mailer)
    {
        $client = new \Google\Client();
        $client->setAuthConfig($serviceAccountJson);
        $client->setScopes(\Google\Service\Calendar::CALENDAR_EVENTS);
        $this->service = new \Google\Service\Calendar($client);
        $this->em = $em;
        $this->mailer = $mailer;
    }

    public function removeReservation(APMBSReservation $reservation) {
        if ($reservation->getGCEventId()) {
            $this->service->events->delete(
                $reservation->getCabane()->getCalendarId(),
                $reservation->getGCEventId()
            );
        }
    }

    public function sendEmailToClient(APMBSReservation $reservation, $title, $message = null, $state = null, array $data = []) {

        if (!$state) {
            $state = $reservation->getStatus();
        }

        $email = (new TemplatedEmail())
            ->from(new Address($reservation->getCabane()->getFromEmail(), "APMBS {$reservation->getCabane()->getNom()}"))
            ->to(new Address($reservation->getEmail()))
            ->subject($title)
            ->htmlTemplate("emails/$state.html.twig")
            ->context(array_merge($data, [
                'reservation' => $reservation,
                'message' => $message,
            ]));

        $this->mailer->send($email);
    }

    public function listReservations(Cabane $cabane, $start = null, $end = null) {
        $month = intval(date('m'));
        $year = date('Y');
        $timeMin = $start ? $start : (new \DateTimeImmutable($year . '-' . $month . '-01'));
        $timeMax = $end ? $end : $timeMin->modify('+1 months');
        $events = $this->service->events->listEvents($cabane->getCalendarId(), [
            'maxResults' => 2500,
            'singleEvents' => true,
            'orderBy' => 'startTime',
            // Lower bound (exclusive) for an event's end time to filter by
            'timeMin' => $timeMin->format('c'),

            // Upper bound (exclusive) for an event's start time to filter by
            'timeMax' => $timeMax->format('c')
        ]);
 
        return $events->getItems();
    }

    public function googleEventToJSON($events) {

        // Try to find corresponding database reservations if any
        $dbReservations = $this->em->getRepository(APMBSReservation::class)->createQueryBuilder('r')
            ->where('r.gcEventId IN (:ids)')
            ->setParameter('ids', array_map(function($event) {
                return $event->getId();
            }, $events))
            ->getQuery()
            ->execute();

        $res = [];
        /** @var Event $event */
        foreach ($events as $event) {
            $start = $event->getStart()->getDateTime();
            $end = $event->getEnd()->getDateTime();

            if (!$start || !$end) {
                // Full day event
                $start = $event->getStart()->getDate();
                $end = $event->getEnd()->getDate();
            }

            $blockStart = true;
            $blockEnd = true;
            foreach ($dbReservations as $i) {
                if ($i->getGCEventId() === $event->getId()) {
                    $blockStart = $i->getBlockStartDay();
                    $blockEnd = $i->getBlockEndDay();
                    break;
                }
            }

            $res[] = [
                'id' => $event->getId(),
                'start' => $start,
                'end' => $end,
                'status' => APMBSReservation::ACCEPTED,
                'blockStartDay' => $blockStart,
                'blockEndDay' => $blockEnd,
            ];
        }

        return $res;
    }

    public function updateReservation(APMBSReservation $reservation) {

        $service = $this->service;
        $event = $this->reservationToGoogleEvent($reservation);

        // If google calendar event, update, otherwise insert new event in calendar
        if ($reservation->getGCEventId()) {
            $result = $service->events->update(
                $reservation->getCabane()->getCalendarId(),
                $reservation->getGCEventId(),
                $event);
            return $result->getId();
        } else {
            $result = $service->events->insert(
                $reservation->getCabane()->getCalendarId(),
                $event);
            $reservation->setGCEventId($result->getId());
            $this->em->persist($reservation);
            $this->em->flush();

            return $result->getId();
        }
    }

    public function deleteReservation(APMBSReservation $reservation) {
        if (!$reservation->getGCEventId()) {
            return;
        }
        
        try {
            $service = $this->service;
            $service->events->delete(
                $reservation->getCabane()->getCalendarId(),
                $reservation->getGCEventId()
            );
        } catch (\Exception $e) {
            dump($e);
        }

        $reservation->setGCEventId(null);
        $this->em->persist($reservation);
        $this->em->flush();
    }

    private function reservationToGoogleEvent(APMBSReservation $reservation) {

        $event = new \Google\Service\Calendar\Event();

        $organiser = new \Google\Service\Calendar\EventOrganizer();
        $organiser->setEmail($reservation->getEmail());
        $organiser->setDisplayName($reservation->getPrenom() . " " . $reservation->getNom());
        $event->setOrganizer($organiser);
        $event->setSummary($reservation->getTitle());

        $event->setDescription("Téléphone: " . $reservation->getPhone());

        $start = new \Google\Service\Calendar\EventDateTime();
        $start->setDateTime($reservation->getStart()->format(\DateTime::RFC3339));
        $event->setStart($start);

        $end = new \Google\Service\Calendar\EventDateTime();
        $end->setDateTime($reservation->getEnd()->format(\DateTime::RFC3339));
        $event->setEnd($end);

        if ($reservation->getStatus() === APMBSReservation::PENDING) {
            $event->setColorId("5");
        } else {
            $event->setColorId("1");
        }

        // $event->setLocation($reservation->getCabane()->getLocation());
        return $event;
    }
}