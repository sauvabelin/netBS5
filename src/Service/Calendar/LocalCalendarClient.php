<?php

namespace App\Service\Calendar;

use Google\Service\Calendar\Event;

/**
 * Calendar backend for dev and test: events live in a JSON file instead of
 * Google. Delete the file to start from an empty calendar.
 *
 * Unlike Google, updating an unknown id creates the event and deleting one is
 * a no-op, so reservations copied from prod (whose event ids only exist in
 * the real calendar) can still be accepted, modified and cancelled locally.
 */
class LocalCalendarClient implements CalendarClientInterface
{
    public function __construct(private readonly string $storagePath)
    {
    }

    public function listEvents(string $calendarId, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $events = array_map(
            fn (array $data) => new Event($data),
            array_values($this->load()[$calendarId] ?? [])
        );

        $events = array_filter($events, fn (Event $event) =>
            self::startOf($event) < $end && self::endOf($event) > $start
        );
        usort($events, fn (Event $a, Event $b) => self::startOf($a) <=> self::startOf($b));

        return array_values($events);
    }

    public function listEventsBatch(array $calendarIds, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $result = [];
        foreach ($calendarIds as $calendarId) {
            $result[$calendarId] = $this->listEvents($calendarId, $start, $end);
        }

        return $result;
    }

    public function insertEvent(string $calendarId, Event $event): Event
    {
        return $this->updateEvent($calendarId, 'local' . bin2hex(random_bytes(12)), $event);
    }

    public function updateEvent(string $calendarId, string $eventId, Event $event): Event
    {
        $stored = new Event(json_decode(json_encode($event->toSimpleObject()), true));
        $stored->setId($eventId);

        $data = $this->load();
        $data[$calendarId][$eventId] = json_decode(json_encode($stored->toSimpleObject()), true);
        $this->save($data);

        return $stored;
    }

    public function deleteEvent(string $calendarId, string $eventId): void
    {
        $data = $this->load();
        unset($data[$calendarId][$eventId]);
        $this->save($data);
    }

    private static function startOf(Event $event): \DateTimeImmutable
    {
        return new \DateTimeImmutable($event->getStart()->getDateTime() ?: $event->getStart()->getDate());
    }

    private static function endOf(Event $event): \DateTimeImmutable
    {
        return new \DateTimeImmutable($event->getEnd()->getDateTime() ?: $event->getEnd()->getDate());
    }

    private function load(): array
    {
        if (!is_file($this->storagePath)) {
            return [];
        }

        return json_decode(file_get_contents($this->storagePath), true, flags: JSON_THROW_ON_ERROR) ?? [];
    }

    private function save(array $data): void
    {
        if (!is_dir(dirname($this->storagePath))) {
            mkdir(dirname($this->storagePath), 0775, true);
        }

        file_put_contents($this->storagePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}
