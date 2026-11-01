<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle\Rule;

use NetBS\FichierBundle\Entity\Famille;
use App\Entity\BSMembre;
use App\Tests\Support\InMemoryFactureTrait;
use Ovesco\FacturationBundle\Rule\Block\DebiteurFamilleBlock;
use Ovesco\FacturationBundle\Rule\Block\ImprimeeBlock;
use Ovesco\FacturationBundle\Rule\Block\NombreCreancesBlock;
use Ovesco\FacturationBundle\Rule\Block\NombreRappelsBlock;
use Ovesco\FacturationBundle\Rule\FactureContext;
use PHPUnit\Framework\TestCase;

final class FactureBlocksTest extends TestCase
{
    use InMemoryFactureTrait;

    public function test_nombre_rappels(): void
    {
        $block = new NombreRappelsBlock();
        $this->assertTrue($block->matches($this->facture(rappels: 2), ['op' => 'gt', 'nombre' => 1]));
        $this->assertFalse($block->matches($this->facture(rappels: 1), ['op' => 'gt', 'nombre' => 1]));
        $this->assertSame('le nombre de rappels est > 1', $block->describe(['op' => '>', 'nombre' => '1'], false));
        $this->assertSame("le nombre de rappels n'est pas > 1", $block->describe(['op' => '>', 'nombre' => '1'], true));
        $this->assertSame('le nombre de rappels est', $block->label());
        $this->assertNull($block->negatedLabel());
    }

    public function test_imprimee(): void
    {
        $block = new ImprimeeBlock();
        $this->assertTrue($block->matches($this->facture(rappels: 1, printed: true), []));
        $this->assertFalse($block->matches($this->facture(), []));
        $this->assertSame('la facture a déjà été imprimée', $block->describe([], false));
        $this->assertSame("la facture n'a jamais été imprimée", $block->describe([], true));
        $this->assertSame('la facture a déjà été imprimée', $block->label());
        $this->assertSame("la facture n'a jamais été imprimée", $block->negatedLabel());
    }

    public function test_debiteur_famille(): void
    {
        $block = new DebiteurFamilleBlock();
        $this->assertTrue($block->matches($this->facture(debiteur: new Famille()), []));
        $this->assertFalse($block->matches($this->facture(debiteur: new BSMembre()), []));
        $this->assertSame('le débiteur est une famille', $block->describe([], false));
        $this->assertSame("le débiteur n'est pas une famille", $block->describe([], true));
        $this->assertSame("le débiteur n'est pas une famille", $block->negatedLabel());
    }

    public function test_nombre_creances(): void
    {
        $block = new NombreCreancesBlock();
        $this->assertTrue($block->matches($this->facture(creances: 1), ['op' => 'eq', 'nombre' => 1]));
        $this->assertFalse($block->matches($this->facture(creances: 2), ['op' => 'eq', 'nombre' => 1]));
        $this->assertSame('le nombre de créances est = 1', $block->describe(['op' => '=', 'nombre' => '1'], false));
        $this->assertNull($block->negatedLabel());
    }

    public function test_context_keeps_legacy_variables(): void
    {
        $context = new FactureContext();
        $famille = new Famille();
        $facture = $this->facture(debiteur: $famille);

        $this->assertSame(['facture', 'debiteur', 'isFamille'], array_keys($context->variables()));
        $this->assertSame(['facture' => $facture, 'debiteur' => $famille, 'isFamille' => true], $context->expressionValues($facture));
    }
}
