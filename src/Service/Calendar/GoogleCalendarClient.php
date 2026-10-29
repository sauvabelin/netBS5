<?php

namespace App\Service\Calendar;

use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\Events;

class GoogleCalendarClient implements CalendarClientInterface
{
    private ?Calendar $service = null;

    public function __construct(private readonly array $serviceAccountJson)
    {
    }

    public function listEvents(string $calendarId, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->service()->events->listEvents($calendarId, $this->listParams($start, $end))->getItems();
    }

    public function listEventsBatch(array $calendarIds, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $service = $this->service();
        $client  = $service->getClient();
        $client->setUseBatch(true);

        try {
            $batch  = $service->createBatch();
            $params = $this->listParams($start, $end);
            foreach ($calendarIds as $calendarId) {
                $batch->add($service->events->listEvents($calendarId, $params), $calendarId);
            }
            $responses = $batch->execute();
        } finally {
            $client->setUseBatch(false);
        }

        $result = [];
        foreach ($calendarIds as $calendarId) {
            $response = $responses["response-$calendarId"] ?? null;
            $result[$calendarId] = $response instanceof Events ? $response->getItems() : [];
        }

        return $result;
    }

    public function insertEvent(string $calendarId, Event $event): Event
    {
        return $this->service()->events->insert($calendarId, $event);
    }

    public function updateEvent(string $calendarId, string $eventId, Event $event): Event
    {
        return $this->service()->events->update($calendarId, $eventId, $event);
    }

    public function deleteEvent(string $calendarId, string $eventId): void
    {
        $this->service()->events->delete($calendarId, $eventId);
    }

    private function listParams(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return [
            'maxResults'   => 2500,
            'singleEvents' => true,
            'orderBy'      => 'startTime',
            'timeMin'      => $start->format('c'),
            'timeMax'      => $end->format('c'),
        ];
    }

    private function service(): Calendar
    {
        if (!$this->service) {
            $client = new \Google\Client();
            $client->setAuthConfig($this->serviceAccountJson);
            $client->setScopes(Calendar::CALENDAR_EVENTS);
            $this->service = new Calendar($client);
        }

        return $this->service;
    }
}
