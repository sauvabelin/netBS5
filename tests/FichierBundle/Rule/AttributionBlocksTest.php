<?php

declare(strict_types=1);

namespace App\Tests\FichierBundle\Rule;

use App\Tests\Support\InMemoryFichierTrait;
use NetBS\FichierBundle\Rule\AttributionInSousGroupe;
use NetBS\FichierBundle\Rule\AttributionSousGroupeContext;
use NetBS\FichierBundle\Rule\Block\AttributionFonctionPoidsBlock;
use NetBS\FichierBundle\Rule\Block\AttributionFonctionsBlock;
use NetBS\FichierBundle\Rule\Block\AttributionGroupeTypesBlock;
use NetBS\FichierBundle\Rule\Block\AttributionProfondeurBlock;
use PHPUnit\Framework\TestCase;

final class AttributionBlocksTest extends TestCase
{
    use InMemoryFichierTrait;

    private AttributionInSousGroupe $subject;

    protected function setUp(): void
    {
        $patrouille    = $this->groupe(3, $this->groupeType(9));
        $attribution   = $this->attribute($this->membre(1), $patrouille, $this->fonction(5, 40));
        $this->subject = new AttributionInSousGroupe($attribution, 2);
    }

    public function test_fonctions(): void
    {
        $block = new AttributionFonctionsBlock();
        $this->assertTrue($block->matches($this->subject, ['fonctions' => [5]]));
        $this->assertFalse($block->matches($this->subject, ['fonctions' => [6]]));
        $this->assertStringContainsString('CP', $block->describe(['fonctions' => 'CP'], false));
        $this->assertNotNull($block->negatedLabel());
    }

    public function test_fonction_poids(): void
    {
        $block = new AttributionFonctionPoidsBlock();
        $this->assertTrue($block->matches($this->subject, ['op' => 'ge', 'poids' => 40]));
        $this->assertFalse($block->matches($this->subject, ['op' => 'gt', 'poids' => 40]));
        $this->assertStringContainsString('≥ 40', $block->describe(['op' => '≥', 'poids' => '40'], false));
        $this->assertNull($block->negatedLabel());
    }

    public function test_groupe_types(): void
    {
        $block = new AttributionGroupeTypesBlock();
        $this->assertTrue($block->matches($this->subject, ['types' => [9]]));
        $this->assertFalse($block->matches($this->subject, ['types' => [1]]));
        $this->assertStringContainsString('Patrouille', $block->describe(['types' => 'Patrouille'], false));
        $this->assertNotNull($block->negatedLabel());
    }

    public function test_profondeur(): void
    {
        $block = new AttributionProfondeurBlock();
        $this->assertTrue($block->matches($this->subject, ['op' => 'le', 'profondeur' => 2]));
        $this->assertFalse($block->matches($this->subject, ['op' => 'le', 'profondeur' => 1]));
        $this->assertStringContainsString('≤ 1', $block->describe(['op' => '≤', 'profondeur' => '1'], false));
        $this->assertNull($block->negatedLabel());
    }

    public function test_context_values(): void
    {
        $values = (new AttributionSousGroupeContext())->expressionValues($this->subject);
        $this->assertSame($this->subject->attribution, $values['attribution']);
        $this->assertSame(2, $values['profondeur']);
        $this->assertSame(3, $values['groupe']->getId());
        $this->assertSame(5, $values['fonction']->getId());
        $this->assertSame(1, $values['membre']->getId());
        $this->assertSame(array_keys($values), array_keys((new AttributionSousGroupeContext())->variables()));
    }
}
