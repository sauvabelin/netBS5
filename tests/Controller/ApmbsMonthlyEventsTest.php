<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\APMBSReservation;
use App\Service\GoogleCalendarManager;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/** The public booking calendar of a cabane must only show that cabane's pending requests. */
final class ApmbsMonthlyEventsTest extends WebTestCase
{
    private Connection $db;

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        $this->db->executeStatement("DELETE r FROM sauvabelin_apmbs_reservations r JOIN apmbs_cabanes c ON c.id = r.cabane_id WHERE c.nom LIKE 'apmbs-events-test%'");
        $this->db->executeStatement("DELETE FROM apmbs_cabanes WHERE nom LIKE 'apmbs-events-test%'");
    }

    private function insertCabane(string $nom): int
    {
        $this->db->insert('apmbs_cabanes', ['nom' => $nom, 'from_email' => 'x@example.test', 'calendar_id' => 'x', 'availability_rule' => '']);

        return (int) $this->db->lastInsertId();
    }

    public function test_pending_reservations_of_another_cabane_are_not_listed(): void
    {
        $client   = static::createClient();
        $this->db = static::getContainer()->get('doctrine.dbal.default_connection');
        $this->cleanUp();

        $calendar = $this->createMock(GoogleCalendarManager::class);
        $calendar->method('listReservations')->willReturn([]);
        $calendar->method('googleEventToJSON')->willReturn([]);
        static::getContainer()->set(GoogleCalendarManager::class, $calendar);

        $shown = $this->insertCabane('apmbs-events-test shown');
        $other = $this->insertCabane('apmbs-events-test other');
        $this->db->insert('sauvabelin_apmbs_reservations', [
            'cabane_id' => $other, 'status' => APMBSReservation::PENDING, 'start' => '2026-06-10 10:00:00', 'end' => '2026-06-11 10:00:00',
            'prenom' => 'A', 'nom' => 'B', 'rue' => 'R', 'npa' => '1000', 'localite' => 'L', 'email' => 'a@example.test',
            'phone' => '0', 'unite' => 'U', 'block_start_day' => 0, 'block_end_day' => 0, 'estimated_price' => 0, 'final_price' => 0,
        ]);

        $client->request('GET', "/api/v1/public/netBS/apmbs/cabane-monthly-events/$shown?start=2026-06-01&end=2026-06-30");

        $this->assertResponseIsSuccessful();
        $this->assertSame([], json_decode((string) $client->getResponse()->getContent(), true));
    }
}
