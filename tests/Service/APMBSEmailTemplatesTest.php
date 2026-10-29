<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Entity\Intendant;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Renders every APMBS email, HTML and text, for the client and for the
 * internal copy, with the context APMBSMailer and the controllers pass.
 */
class APMBSEmailTemplatesTest extends KernelTestCase
{
    public static function templates(): iterable
    {
        foreach (['pending', 'accepted', 'refused', 'cancelled', 'modification', 'invoice'] as $name) {
            yield "$name (client)" => [$name, false];
            yield "$name (copy)" => [$name, true];
        }
    }

    /** @dataProvider templates */
    public function test_renders_html_and_text(string $name, bool $toApmbs): void
    {
        $html = $this->render("emails/$name.html.twig", $toApmbs);
        $text = $this->render("emails/$name.txt.twig", $toApmbs);

        $this->assertStringContainsString('APMBS |', $html);
        $this->assertMatchesRegularExpression('/Réservation n°(&nbsp;|\x{A0})42/u', $html);
        $this->assertStringContainsString('10.11.2026', $html);
        $this->assertStringContainsString('Merci de laisser la cabane propre', $html);
        $this->assertStringContainsString('style="', $html, 'CSS should be inlined');
        $this->assertStringNotContainsString('<script', $html);

        $this->assertStringContainsString('Réservation n° 42', $text);
        $this->assertStringContainsString('10.11.2026', $text);
        $this->assertStringContainsString("L'APMBS", $text);
        $this->assertStringNotContainsString('<', $text, 'text part must not contain HTML');

        if ($toApmbs) {
            $this->assertStringContainsString('Copie interne', $html);
            $this->assertStringContainsString('/apmbs/reservation/42', $html);
            $this->assertStringNotContainsString('Bonjour', $html);
        } else {
            $this->assertStringContainsString('Bonjour Jeanne', $html);
            $this->assertStringNotContainsString('Copie interne', $html);
            $this->assertStringNotContainsString('/apmbs/reservation/42"', $html);
        }
    }

    public function test_client_answer_buttons_are_only_in_the_client_version(): void
    {
        $client = $this->render('emails/modification.html.twig', false);
        $copy = $this->render('emails/modification.html.twig', true);

        $this->assertStringContainsString('token=signed-accept', $client);
        $this->assertStringContainsString('token=signed-reject', $client);
        $this->assertStringNotContainsString('/client/accept/', $copy);
        $this->assertStringNotContainsString('/client/reject/', $copy);
    }

    public function test_user_input_is_escaped(): void
    {
        $html = $this->render('emails/pending.html.twig', false, '<b>hack</b>');

        $this->assertStringNotContainsString('<b>hack</b>', $html);
        $this->assertStringContainsString('&lt;b&gt;hack&lt;/b&gt;', $html);
    }

    private function render(string $template, bool $toApmbs, string $description = "Camp d'automne\nAvec 20 enfants"): string
    {
        self::bootKernel();

        return self::getContainer()->get(Environment::class)->render($template, [
            'reservation' => $this->reservation($description),
            'message' => "Merci de laisser la cabane propre.\nBon séjour !",
            'toApmbs' => $toApmbs,
            'oldStart' => new \DateTime('2026-11-09 10:00'),
            'oldEnd' => new \DateTime('2026-11-11 16:00'),
            'autreFrais' => ['message' => 'Bois de chauffage', 'montant' => 30],
            'acceptUrl' => 'https://netbs.example/client/accept/42?token=signed-accept',
            'rejectUrl' => 'https://netbs.example/client/reject/42?token=signed-reject',
        ]);
    }

    private function reservation(string $description): APMBSReservation
    {
        $cabane = new Cabane();
        $cabane->setNom('La Moillettaz');
        $cabane->setFromEmail('moillettaz@sauvabelin.ch');
        $cabane->setConfirmedEmail("Votre réservation est confirmée.\nÀ bientôt.");
        $cabane->setConditions("Pas de feu à l'intérieur\nRendre les clés");

        $intendant = new Intendant();
        $intendant->setNom('Paul Martin');
        $intendant->setPhone('079 111 11 11');

        $reservation = new APMBSReservation();
        (new \ReflectionProperty(APMBSReservation::class, 'id'))->setValue($reservation, 42);
        $reservation->setCabane($cabane);
        $reservation->setStatus(APMBSReservation::PENDING);
        $reservation->setPrenom('Jeanne');
        $reservation->setNom('Dupont');
        $reservation->setUnite('Brigade');
        $reservation->setEmail('jeanne@example.com');
        $reservation->setPhone('079 000 00 00');
        $reservation->setRue('Rue du Lac 1');
        $reservation->setNpa('1000');
        $reservation->setLocalite('Lausanne');
        $reservation->setDescription($description);
        $reservation->setStart(new \DateTime('2026-11-10 10:00'));
        $reservation->setEnd(new \DateTime('2026-11-12 16:00'));
        $reservation->setIntendantDebut($intendant);
        $reservation->setIntendantFin($intendant);
        $reservation->setEstimatedPrice(150);
        $reservation->setFinalPrice(150);

        return $reservation;
    }
}
