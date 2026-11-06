<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle;

use App\Tests\Support\InMemoryFactureTrait;
use NetBS\FichierBundle\Entity\Famille;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Rule\FactureContext;
use Ovesco\FacturationBundle\Service\FactureTextRenderer;
use Ovesco\FacturationBundle\Service\LegacyFactureText;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * The texts of the prod-copy facture models (APMBS excluded), copied on 2026-10-31 into
 * Fixtures/facture_model_texts.json: converted to the new format, they must render exactly
 * as the old renderer renders the original.
 */
final class LegacyFactureTextTest extends TestCase
{
    use InMemoryFactureTrait;

    private function renderer(): FactureTextRenderer
    {
        return new FactureTextRenderer(new LegacyFactureText(), new FactureContext(), new NullLogger());
    }

    /** @return iterable<string,array{string}> */
    public static function realTexts(): iterable
    {
        $models = json_decode(file_get_contents(__DIR__ . '/Fixtures/facture_model_texts.json'), true);
        foreach ($models as $model) {
            foreach (['titre', 'top', 'bottom'] as $field) {
                yield "model {$model['id']} $field" => [$model[$field]];
            }
        }
    }

    /** @return list<Facture> */
    private function factures(): array
    {
        $famille = new Famille();
        $famille->setNom('Dupont');

        return [
            $this->facture(rappels: 0, creances: 1, debiteur: $famille),
            $this->facture(rappels: 1, creances: 2, printed: true, debiteur: $famille),
            $this->facture(rappels: 2, creances: 1, printed: true, debiteur: $famille),
        ];
    }

    /** @dataProvider realTexts */
    public function test_converted_real_text_renders_like_the_old_renderer(string $old): void
    {
        $legacy    = new LegacyFactureText();
        $converted = LegacyFactureText::convert($old);
        $compared  = 0;

        foreach ($this->factures() as $facture) {
            // the old renderer throws on a null impression date; such factures never got that model
            try {
                $expected = $legacy->render($old, $facture);
            } catch (\Throwable) {
                continue;
            }
            $this->assertSame($expected, $this->renderer()->render($converted, $facture, 2));
            $compared++;
        }
        $this->assertGreaterThan(0, $compared);
    }

    public function test_known_patterns_become_placeholders(): void
    {
        $this->assertSame('Pour {debiteur}', LegacyFactureText::convert('Pour [debiteur]'));
        $this->assertSame('n°{facture.numero}', LegacyFactureText::convert('n°[facture.getFactureId()]'));
        $this->assertSame('{nombre_rappels}ème', LegacyFactureText::convert('[facture.getRappels().count()]ème'));
        $this->assertSame('le {derniere_impression}', LegacyFactureText::convert('le [facture.getLatestImpression().format("d.m.Y")]'));
        $this->assertSame("{pluriel_creances:l'objet suivant|les objets suivants}",
            LegacyFactureText::convert('[(facture.getCreances().count() == 1 ? "l\'objet suivant" : "les objets suivants")]'));
    }

    public function test_line_breaks_and_escapes(): void
    {
        $this->assertSame("Madame,\n\nNous", LegacyFactureText::convert("Madame,\\n\\n\r\nNous"));
        $this->assertSame("l'objet", LegacyFactureText::convert("l'objet"));
        $this->assertNull(LegacyFactureText::convert(null));
    }

    public function test_unknown_expression_kept(): void
    {
        $this->assertSame('Total [facture.getMontant()]', LegacyFactureText::convert('Total [facture.getMontant()]'));
    }
}
