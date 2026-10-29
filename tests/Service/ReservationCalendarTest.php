<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Service\Calendar\LocalCalendarClient;
use App\Service\ReservationCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Google\Service\Calendar\Event;
use PHPUnit\Framework\TestCase;

class ReservationCalendarTest extends TestCase
{
    private string $path;
    private LocalCalendarClient $client;
    private ReservationCalendar $calendar;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/reservation_calendar_' . bin2hex(random_bytes(6)) . '.json';
        $this->client = new LocalCalendarClient($this->path);
        $this->calendar = new ReservationCalendar($this->client, $this->createMock(EntityManagerInterface::class));
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_first_update_inserts_an_event_and_stores_its_id(): void
    {
        $reservation = $this->reservation(APMBSReservation::ACCEPTED);

        $eventId = $this->calendar->updateReservation($reservation);

        $this->assertSame($eventId, $reservation->getGCEventId());
        $events = $this->eventsInNovember();
        $this->assertCount(1, $events);
        $this->assertSame($eventId, $events[0]->getId());
        $this->assertSame('Jeanne Dupont - Brigade', $events[0]->getSummary());
        $this->assertSame('1', $events[0]->getColorId());
    }

    public function test_pending_reservations_get_the_pending_colour(): void
    {
        $this->calendar->updateReservation($this->reservation(APMBSReservation::PENDING));

        $this->assertSame('5', $this->eventsInNovember()[0]->getColorId());
    }

    public function test_second_update_moves_the_existing_event(): void
    {
        $reservation = $this->reservation(APMBSReservation::ACCEPTED);
        $eventId = $this->calendar->updateReservation($reservation);

        $reservation->setStart(new \DateTime('2026-11-20 10:00'));
        $reservation->setEnd(new \DateTime('2026-11-21 16:00'));
        $this->calendar->updateReservation($reservation);

        $events = $this->eventsInNovember();
        $this->assertCount(1, $events);
        $this->assertSame($eventId, $events[0]->getId());
        $this->assertStringStartsWith('2026-11-20T10:00:00', $events[0]->getStart()->getDateTime());
    }

    public function test_delete_removes_the_event_and_clears_the_id(): void
    {
        $reservation = $this->reservation(APMBSReservation::ACCEPTED);
        $this->calendar->updateReservation($reservation);

        $this->calendar->deleteReservation($reservation);

        $this->assertNull($reservation->getGCEventId());
        $this->assertSame([], $this->eventsInNovember());
    }

    public function test_list_reservations_reads_the_cabane_calendar(): void
    {
        $reservation = $this->reservation(APMBSReservation::ACCEPTED);
        $this->calendar->updateReservation($reservation);

        $events = $this->calendar->listReservations(
            $reservation->getCabane(),
            new \DateTimeImmutable('2026-11-01'),
            new \DateTimeImmutable('2026-12-01')
        );

        $this->assertSame([$reservation->getGCEventId()], array_map(fn (Event $e) => $e->getId(), $events));
    }

    public function test_batch_listing_maps_events_for_fullcalendar(): void
    {
        $this->calendar->updateReservation($this->reservation(APMBSReservation::ACCEPTED));

        $result = $this->calendar->listCalendarEventsBatch(['cal-moillettaz', 'cal-empty'], new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-12-01'));

        $this->assertSame([], $result['cal-empty']);
        $this->assertSame('Jeanne Dupont - Brigade', $result['cal-moillettaz'][0]['title']);
        $this->assertStringStartsWith('2026-11-10T10:00:00', $result['cal-moillettaz'][0]['start']);
        $this->assertSame('Téléphone: 079 000 00 00', $result['cal-moillettaz'][0]['extendedProps']['description']);
    }

    /** @return Event[] */
    private function eventsInNovember(): array
    {
        return $this->client->listEvents('cal-moillettaz', new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-12-01'));
    }

    private function reservation(string $status): APMBSReservation
    {
        $cabane = new Cabane();
        $cabane->setNom('La Moillettaz');
        $cabane->setCalendarId('cal-moillettaz');

        $reservation = new APMBSReservation();
        $reservation->setCabane($cabane);
        $reservation->setStatus($status);
        $reservation->setPrenom('Jeanne');
        $reservation->setNom('Dupont');
        $reservation->setUnite('Brigade');
        $reservation->setEmail('jeanne@example.com');
        $reservation->setPhone('079 000 00 00');
        $reservation->setStart(new \DateTime('2026-11-10 10:00'));
        $reservation->setEnd(new \DateTime('2026-11-12 16:00'));

        return $reservation;
    }
}
