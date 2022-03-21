<?php

namespace App\Service;

use App\Entity\APMBSReservation;
use Google_Client;

class GoogleCalendarManager {

    /**
     * @var \Google\Service\Calendar
     */
    private $service;

    public function __construct(Google_Client $client)
    {
        $client->setApplicationName('netBS');
        $client->setScopes(\Google\Service\Calendar::CALENDAR_EVENTS);
        $this->service = new \Google\Service\Calendar($client);
    }

    public function removeReservation(APMBSReservation $reservation) {
        if ($reservation->getGCEventId()) {
            $this->service->events->delete(
                $reservation->getCabane()->getCalendarId(),
                $reservation->getGCEventId()
            );
        }
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
            return $result->getId();
        }
    }

    private function reservationToGoogleEvent(APMBSReservation $reservation) {

        $event = new \Google\Service\Calendar\Event();

        $organiser = new \Google\Service\Calendar\EventOrganizer();
        $organiser->setEmail($reservation->getEmail());
        $organiser->setDisplayName($reservation->getPrenom() . " " . $reservation->getNom());
        $event->setOrganizer($organiser);

        $event->setDescription("Téléphone: " . $reservation->getPhone());

        $start = new \Google\Service\Calendar\EventDateTime();
        $start->setDateTime($reservation->getStart());
        $event->setStart($start);

        $end = new \Google\Service\Calendar\EventDateTime();
        $end->setDateTime($reservation->getEnd());
        $event->setEnd($end);

        $event->setLocation($reservation->getCabane()->getLocation());
        return $event;
    }
}