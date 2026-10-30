<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RequestContext;
use Twig\Environment;

class APMBSEmailLinksTest extends KernelTestCase
{
    public function test_links_follow_the_current_host_instead_of_prod(): void
    {
        self::bootKernel();
        $context = self::getContainer()->get('router')->getContext();
        $context->setScheme('http')->setHost('netbs.localhost')->setHttpPort(8080);

        $html = self::getContainer()->get(Environment::class)->render('emails/refused.html.twig', [
            'reservation' => $this->reservation(),
            'message' => null,
            'toApmbs' => true,
        ]);

        $this->assertStringNotContainsString('netbs.sauvabelin.ch', $html);
        $this->assertStringContainsString('href="http://netbs.localhost:8080/netBS/bs/apmbs/reservation/42"', $html);
        $this->assertMatchesRegularExpression('#src="http://netbs\.localhost:8080/[^"]*logo[^"]*\.png"#', $html);
    }

    private function reservation(): APMBSReservation
    {
        $cabane = new Cabane();
        $cabane->setNom('La Moillettaz');
        $cabane->setFromEmail('moillettaz@sauvabelin.ch');

        $reservation = new APMBSReservation();
        (new \ReflectionProperty(APMBSReservation::class, 'id'))->setValue($reservation, 42);
        $reservation->setCabane($cabane);
        $reservation->setStatus(APMBSReservation::REFUSED);
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
