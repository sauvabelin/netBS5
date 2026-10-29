<?php

declare(strict_types=1);

namespace App\Tests\Service\Calendar;

use App\Service\Calendar\LocalCalendarClient;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use PHPUnit\Framework\TestCase;

class LocalCalendarClientTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/local_calendar_' . bin2hex(random_bytes(6)) . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_inserted_event_gets_an_id_and_is_listed(): void
    {
        $client = new LocalCalendarClient($this->path);

        $inserted = $client->insertEvent('cal-a', $this->event('Camp', '2026-11-10T10:00:00+01:00', '2026-11-12T16:00:00+01:00'));

        $this->assertNotEmpty($inserted->getId());
        $events = $client->listEvents('cal-a', new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-12-01'));
        $this->assertCount(1, $events);
        $this->assertSame($inserted->getId(), $events[0]->getId());
        $this->assertSame('Camp', $events[0]->getSummary());
        $this->assertSame('2026-11-10T10:00:00+01:00', $events[0]->getStart()->getDateTime());
    }

    public function test_events_persist_across_instances(): void
    {
        $id = (new LocalCalendarClient($this->path))
            ->insertEvent('cal-a', $this->event('Camp', '2026-11-10T10:00:00+01:00', '2026-11-12T16:00:00+01:00'))
            ->getId();

        $events = (new LocalCalendarClient($this->path))
            ->listEvents('cal-a', new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-12-01'));

        $this->assertSame([$id], array_map(fn (Event $e) => $e->getId(), $events));
    }

    public function test_list_only_returns_overlapping_events_of_that_calendar_sorted_by_start(): void
    {
        $client = new LocalCalendarClient($this->path);
        $client->insertEvent('cal-a', $this->event('Late', '2026-11-20T10:00:00+01:00', '2026-11-21T10:00:00+01:00'));
        $client->insertEvent('cal-a', $this->event('Spans start', '2026-10-30T10:00:00+01:00', '2026-11-02T10:00:00+01:00'));
        $client->insertEvent('cal-a', $this->event('Before', '2026-10-01T10:00:00+02:00', '2026-10-02T10:00:00+02:00'));
        $client->insertEvent('cal-a', $this->event('After', '2026-12-05T10:00:00+01:00', '2026-12-06T10:00:00+01:00'));
        $client->insertEvent('cal-b', $this->event('Other cal', '2026-11-15T10:00:00+01:00', '2026-11-16T10:00:00+01:00'));

        $events = $client->listEvents('cal-a', new \DateTimeImmutable('2026-11-01T00:00:00+01:00'), new \DateTimeImmutable('2026-12-01T00:00:00+01:00'));

        $this->assertSame(['Spans start', 'Late'], array_map(fn (Event $e) => $e->getSummary(), $events));
    }

    public function test_all_day_events_are_listed(): void
    {
        $client = new LocalCalendarClient($this->path);
        $event = new Event();
        $event->setSummary('All day');
        $event->setStart(new EventDateTime(['date' => '2026-11-10']));
        $event->setEnd(new EventDateTime(['date' => '2026-11-11']));
        $client->insertEvent('cal-a', $event);

        $events = $client->listEvents('cal-a', new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-12-01'));

        $this->assertSame('2026-11-10', $events[0]->getStart()->getDate());
    }

    public function test_update_replaces_the_event(): void
    {
        $client = new LocalCalendarClient($this->path);
        $id = $client->insertEvent('cal-a', $this->event('Camp', '2026-11-10T10:00:00+01:00', '2026-11-12T16:00:00+01:00'))->getId();

        $updated = $client->updateEvent('cal-a', $id, $this->event('Camp moved', '2026-11-14T10:00:00+01:00', '2026-11-15T16:00:00+01:00'));

        $this->assertSame($id, $updated->getId());
        $events = $client->listEvents('cal-a', new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-12-01'));
        $this->assertCount(1, $events);
        $this->assertSame('Camp moved', $events[0]->getSummary());
    }

    public function test_update_of_unknown_id_creates_it_so_prod_copied_reservations_still_work(): void
    {
        $client = new LocalCalendarClient($this->path);

        $client->updateEvent('cal-a', 'prod-event-id', $this->event('Camp', '2026-11-10T10:00:00+01:00', '2026-11-12T16:00:00+01:00'));

        $events = $client->listEvents('cal-a', new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-12-01'));
        $this->assertSame('prod-event-id', $events[0]->getId());
    }

    public function test_delete_removes_the_event_and_ignores_unknown_ids(): void
    {
        $client = new LocalCalendarClient($this->path);
        $id = $client->insertEvent('cal-a', $this->event('Camp', '2026-11-10T10:00:00+01:00', '2026-11-12T16:00:00+01:00'))->getId();

        $client->deleteEvent('cal-a', $id);
        $client->deleteEvent('cal-a', 'never-existed');

        $this->assertSame([], $client->listEvents('cal-a', new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-12-01')));
    }

    public function test_batch_list_is_keyed_by_calendar_id(): void
    {
        $client = new LocalCalendarClient($this->path);
        $client->insertEvent('cal-a', $this->event('A', '2026-11-10T10:00:00+01:00', '2026-11-11T10:00:00+01:00'));

        $result = $client->listEventsBatch(['cal-a', 'cal-b'], new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-12-01'));

        $this->assertSame(['cal-a', 'cal-b'], array_keys($result));
        $this->assertCount(1, $result['cal-a']);
        $this->assertSame([], $result['cal-b']);
    }

    private function event(string $summary, string $start, string $end): Event
    {
        $event = new Event();
        $event->setSummary($summary);
        $event->setStart(new EventDateTime(['dateTime' => $start]));
        $event->setEnd(new EventDateTime(['dateTime' => $end]));

        return $event;
    }
}
