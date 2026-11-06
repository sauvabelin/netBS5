<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle;

use App\Tests\Support\CollectingLogger;
use App\Tests\Support\InMemoryFactureTrait;
use NetBS\FichierBundle\Entity\Famille;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Rule\FactureContext;
use Ovesco\FacturationBundle\Service\FactureTextRenderer;
use Ovesco\FacturationBundle\Service\LegacyFactureText;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class FactureTextRendererTest extends TestCase
{
    use InMemoryFactureTrait;

    private CollectingLogger $logger;
    private FactureTextRenderer $renderer;

    protected function setUp(): void
    {
        $this->logger   = new CollectingLogger();
        $this->renderer = new FactureTextRenderer(new LegacyFactureText(), new FactureContext(), $this->logger);
    }

    /** A facture with id 42 and a famille débiteur. */
    private function numbered(int $rappels = 0, int $creances = 1, bool $printed = false): Facture
    {
        $famille = new Famille();
        $famille->setNom('Dupont');
        $facture  = $this->facture($rappels, $creances, $printed, $famille);
        $property = new \ReflectionProperty(Facture::class, 'id');
        $property->setAccessible(true);
        $property->setValue($facture, 42);

        return $facture;
    }

    private function render(string $text, ?Facture $facture = null): string
    {
        return $this->renderer->render($text, $facture ?? $this->numbered(), 2);
    }

    public function test_placeholders(): void
    {
        $facture = $this->numbered(rappels: 2, creances: 3, printed: true);

        $this->assertSame('Pour Famille Dupont', $this->render('Pour {debiteur}', $facture));
        $this->assertSame('n° ' . $facture->getFactureId(), $this->render('n° {facture.numero}', $facture));
        $this->assertSame('du 15.01.2026', $this->render('du {facture.date}', $facture));
        $this->assertSame('le 10.03.2026', $this->render('le {derniere_impression}', $facture));
        $this->assertSame('2e rappel, 3 créances', $this->render('{nombre_rappels}e rappel, {nombre_creances} créances', $facture));
        $this->assertSame('le ', $this->render('le {derniere_impression}', $this->numbered()));
        $this->assertSame(array_keys($this->renderer->placeholders()), [
            '{debiteur}', '{facture.numero}', '{facture.date}', '{derniere_impression}', '{nombre_rappels}', '{nombre_creances}', '{pluriel_creances:singulier|pluriel}',
        ]);
    }

    public function test_plural(): void
    {
        $text = "{pluriel_creances:l'objet suivant|les objets suivants}";
        $this->assertSame("l'objet suivant", $this->render($text, $this->numbered(creances: 1)));
        $this->assertSame('les objets suivants', $this->render($text, $this->numbered(creances: 2)));
    }

    public function test_expert_placeholder(): void
    {
        $facture = $this->numbered();
        $this->assertSame('n° ' . $facture->getFactureId(), $this->render('n° [facture.getFactureId()]', $facture));
    }

    public function test_throwing_expression_renders_empty_and_logs(): void
    {
        $this->assertSame('le .', $this->render('le [facture.getLatestImpression().format("d.m.Y")].'));
        $this->assertSame(1, $this->logger->count(LogLevel::WARNING));
    }

    public function test_line_breaks_kept_and_normalized(): void
    {
        $this->assertSame("a\nb\nc", $this->render("a\r\nb\nc"));
    }

    public function test_unknown_placeholder_kept_verbatim(): void
    {
        $this->assertSame('{inconnu}', $this->render('{inconnu}'));
    }

    public function test_format_1_delegates_to_legacy(): void
    {
        $facture = $this->numbered();
        $old     = "Madame,\\n[debiteur]\r\n";
        $this->assertSame((new LegacyFactureText())->render($old, $facture), $this->renderer->render($old, $facture, 1));
        $this->assertSame("Madame,\nFamille Dupont", $this->renderer->render($old, $facture, 1));
    }
}
