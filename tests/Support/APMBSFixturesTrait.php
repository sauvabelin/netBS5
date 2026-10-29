<?php

namespace App\Tests\Support;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use Doctrine\ORM\EntityManagerInterface;

/**
 * A cabane + reservation pair in netbs5_test, created fresh for each test.
 * Calendar writes go to LocalCalendarClient (var/apmbs_calendar.test.json).
 */
trait APMBSFixturesTrait
{
    protected function persistReservation(EntityManagerInterface $em, string $status): APMBSReservation
    {
        $cabane = new Cabane();
        $cabane->setNom('Cabane test');
        $cabane->setFromEmail('cabane@example.com');
        $cabane->setCalendarId('test-calendar');
        $cabane->setAvailabilityRule('{}');

        $reservation = new APMBSReservation();
        $reservation->setCabane($cabane);
        $reservation->setStatus($status);
        $reservation->setPrenom('Jeanne');
        $reservation->setNom('Dupont');
        $reservation->setUnite('Brigade');
        $reservation->setEmail('jeanne@example.com');
        $reservation->setPhone('079 000 00 00');
        $reservation->setRue('Rue du Lac 1');
        $reservation->setNpa('1000');
        $reservation->setLocalite('Lausanne');
        $reservation->setStart(new \DateTime('2026-11-10 10:00'));
        $reservation->setEnd(new \DateTime('2026-11-12 16:00'));

        $em->persist($cabane);
        $em->persist($reservation);
        $em->flush();

        return $reservation;
    }

    protected function reloadReservation(EntityManagerInterface $em, APMBSReservation $reservation): APMBSReservation
    {
        $em->clear();

        return $em->find(APMBSReservation::class, $reservation->getId());
    }
}
