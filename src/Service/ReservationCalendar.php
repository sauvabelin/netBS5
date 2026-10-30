<?php

namespace App\Service;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Service\Calendar\CalendarClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\Calendar\Event;

/**
 * Keeps the cabane calendars in sync with APMBS reservations. Which backend
 * is written to (Google or a local file) depends on the CalendarClientInterface
 * alias in config/services.yaml. Updates gcEventId on the reservation but
 * doesn't flush: callers save it with the rest of their changes.
 */
class ReservationCalendar {

    public function __construct(
        private readonly CalendarClientInterface $client,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function listCalendarEventsBatch(array $calendarIds, \DateTimeInterface $start, \DateTimeInterface $end): array {
        if (empty($calendarIds)) {
            return [];
        }

        return array_map(
            fn (array $events) => $this->mapEventsToArray($events),
            $this->client->listEventsBatch($calendarIds, $start, $end)
        );
    }

    private function mapEventsToArray(array $items): array {
        $result = [];
        foreach ($items as $event) {
            $eventStart = $event->getStart()->getDateTime() ?: $event->getStart()->getDate();
            $eventEnd   = $event->getEnd()->getDateTime() ?: $event->getEnd()->getDate();

            $result[] = [
                'title' => $event->getSummary(),
                'start' => $eventStart,
                'end'   => $eventEnd,
                'url'   => $event->getHtmlLink(),
                'extendedProps' => [
                    'description' => $event->getDescription(),
                    'location'    => $event->getLocation(),
                ],
            ];
        }
        return $result;
    }

    /**
     * @return Event[]
     */
    public function listReservations(Cabane $cabane, $start = null, $end = null) {
        $month = intval(date('m'));
        $year = date('Y');
        $timeMin = $start ? $start : (new \DateTimeImmutable($year . '-' . $month . '-01'));
        $timeMax = $end ? $end : $timeMin->modify('+1 months');

        return $this->client->listEvents($cabane->getCalendarId(), $timeMin, $timeMax);
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

        $event = $this->reservationToGoogleEvent($reservation);
        $calendarId = $reservation->getCabane()->getCalendarId();

        // If google calendar event, update, otherwise insert new event in calendar
        if ($reservation->getGCEventId()) {
            return $this->client->updateEvent($calendarId, $reservation->getGCEventId(), $event)->getId();
        }

        $result = $this->client->insertEvent($calendarId, $event);
        $reservation->setGCEventId($result->getId());

        return $result->getId();
    }

    public function deleteReservation(APMBSReservation $reservation) {
        if (!$reservation->getGCEventId()) {
            return;
        }

        try {
            $this->client->deleteEvent(
                $reservation->getCabane()->getCalendarId(),
                $reservation->getGCEventId()
            );
        } catch (\Exception $e) {
            dump($e);
        }

        $reservation->setGCEventId(null);
    }

    private function reservationToGoogleEvent(APMBSReservation $reservation): Event {

        $event = new Event();

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
