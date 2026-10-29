<?php

namespace App\Service\Calendar;

use Google\Service\Calendar\Event;

/**
 * Raw calendar backend. Prod talks to Google; dev and test use
 * LocalCalendarClient so nothing ever reaches the real cabane calendars.
 */
interface CalendarClientInterface
{
    /**
     * Events overlapping [start, end), expanded and sorted by start time.
     *
     * @return Event[]
     */
    public function listEvents(string $calendarId, \DateTimeInterface $start, \DateTimeInterface $end): array;

    /**
     * @param string[] $calendarIds
     * @return array<string, Event[]> keyed by calendar id, in the given order
     */
    public function listEventsBatch(array $calendarIds, \DateTimeInterface $start, \DateTimeInterface $end): array;

    public function insertEvent(string $calendarId, Event $event): Event;

    public function updateEvent(string $calendarId, string $eventId, Event $event): Event;

    public function deleteEvent(string $calendarId, string $eventId): void;
}
