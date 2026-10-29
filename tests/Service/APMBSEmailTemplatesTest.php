<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\APMBSReservation;
use App\Entity\Intendant;
use App\Tests\Support\APMBSFixturesTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

/**
 * Renders every APMBS email, HTML and text, for the client and for the
 * internal copy, with the context APMBSMailer and the controllers pass.
 */
class APMBSEmailTemplatesTest extends KernelTestCase
{
    use APMBSFixturesTrait;

    protected function setUp(): void
    {
        self::bootKernel();
    }

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
        $reservation = $this->reservation('<b>hack</b>');
        $reservation->getCabane()->setConfirmedEmail("Bienvenue\n<b>vite</b>");
        $html = $this->render('emails/accepted.html.twig', false, $reservation);

        $this->assertStringNotContainsString('<b>hack</b>', $this->render('emails/pending.html.twig', false, $reservation));
        $this->assertMatchesRegularExpression('#Bienvenue<br ?/?>\s*&lt;b&gt;vite&lt;/b&gt;#', $html, 'cabane text keeps its line breaks and is escaped once');
    }

    private function render(string $template, bool $toApmbs, ?APMBSReservation $reservation = null): string
    {
        return self::getContainer()->get(Environment::class)->render($template, [
            'reservation' => $reservation ?? $this->reservation("Camp d'automne\nAvec 20 enfants"),
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
        $reservation = $this->makeReservation(APMBSReservation::PENDING, 42);
        $reservation->getCabane()->setNom('La Moillettaz');
        $reservation->getCabane()->setConfirmedEmail("Votre réservation est confirmée.\nÀ bientôt.");
        $reservation->getCabane()->setConditions("Pas de feu à l'intérieur\nRendre les clés");

        $intendant = new Intendant();
        $intendant->setNom('Paul Martin');
        $intendant->setPhone('079 111 11 11');

        $reservation->setDescription($description);
        $reservation->setIntendantDebut($intendant);
        $reservation->setIntendantFin($intendant);
        $reservation->setEstimatedPrice(150);
        $reservation->setFinalPrice(150);

        return $reservation;
    }
}
