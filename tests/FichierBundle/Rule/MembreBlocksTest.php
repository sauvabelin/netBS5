<?php

declare(strict_types=1);

namespace App\Tests\FichierBundle\Rule;

use App\Tests\Support\InMemoryFichierTrait;
use NetBS\FichierBundle\Rule\Block\MembreAttributionGroupesBlock;
use NetBS\FichierBundle\Rule\Block\MembreFonctionPoidsBlock;
use NetBS\FichierBundle\Rule\Block\MembreFonctionsBlock;
use NetBS\FichierBundle\Rule\Block\MembreGroupeTypesBlock;
use NetBS\FichierBundle\Rule\MembreContext;
use PHPUnit\Framework\TestCase;

/** Tree: branche 1 <- troupe 2 <- patrouille 3. */
final class MembreBlocksTest extends TestCase
{
    use InMemoryFichierTrait;

    public function test_attribution_groupes_modes(): void
    {
        $branche    = $this->groupe(1);
        $troupe     = $this->groupe(2, parent: $branche);
        $patrouille = $this->groupe(3, parent: $troupe);
        $membre     = $this->membre(1);
        $this->attribute($membre, $patrouille, $this->fonction(1));

        $block = new MembreAttributionGroupesBlock();
        $this->assertTrue($block->matches($membre, ['groupes' => [3], 'sous_groupes' => 'aucun']));
        $this->assertFalse($block->matches($membre, ['groupes' => [2], 'sous_groupes' => 'aucun']));
        $this->assertTrue($block->matches($membre, ['groupes' => [2], 'sous_groupes' => 'direct']));
        $this->assertFalse($block->matches($membre, ['groupes' => [1], 'sous_groupes' => 'direct']));
        $this->assertTrue($block->matches($membre, ['groupes' => [1], 'sous_groupes' => 'tous']));
        $this->assertTrue($block->matches($membre, ['groupes' => ['1', 99], 'sous_groupes' => 'tous']));
    }

    public function test_attribution_groupes_cycle_terminates(): void
    {
        $a = $this->groupe(1);
        $b = $this->groupe(2, parent: $a);
        $a->setParent($b);
        $membre = $this->membre(1);
        $this->attribute($membre, $b, $this->fonction(1));

        $this->assertFalse((new MembreAttributionGroupesBlock())->matches($membre, ['groupes' => [99], 'sous_groupes' => 'tous']));
    }

    public function test_inactive_attributions_ignored(): void
    {
        $groupe = $this->groupe(1, $this->groupeType(7));
        $membre = $this->membre(1);
        $this->attribute($membre, $groupe, $this->fonction(5, 150), active: false);

        $this->assertFalse((new MembreAttributionGroupesBlock())->matches($membre, ['groupes' => [1], 'sous_groupes' => 'aucun']));
        $this->assertFalse((new MembreFonctionPoidsBlock())->matches($membre, ['op' => 'gt', 'poids' => 99]));
        $this->assertFalse((new MembreFonctionsBlock())->matches($membre, ['fonctions' => [5]]));
        $this->assertFalse((new MembreGroupeTypesBlock())->matches($membre, ['types' => [7]]));
    }

    public function test_fonction_poids(): void
    {
        $membre = $this->membre(1);
        $this->attribute($membre, $this->groupe(1), $this->fonction(1, 150));
        $block = new MembreFonctionPoidsBlock();

        $this->assertTrue($block->matches($membre, ['op' => 'gt', 'poids' => 99]));
        $this->assertFalse($block->matches($membre, ['op' => 'gt', 'poids' => 150]));
        $this->assertSame('le membre a une fonction de poids > 99', $block->describe(['op' => '>', 'poids' => '99'], false));
        $this->assertSame("le membre n'a pas de fonction de poids > 99", $block->describe(['op' => '>', 'poids' => '99'], true));
        $this->assertSame('le membre a une fonction de poids', $block->label());
        $this->assertNull($block->negatedLabel());
    }

    public function test_fonctions(): void
    {
        $membre = $this->membre(1);
        $this->attribute($membre, $this->groupe(1), $this->fonction(5));
        $block = new MembreFonctionsBlock();

        $this->assertTrue($block->matches($membre, ['fonctions' => [4, 5]]));
        $this->assertFalse($block->matches($membre, ['fonctions' => [4]]));
        $this->assertSame('le membre a la fonction CP, CL', $block->describe(['fonctions' => 'CP, CL'], false));
        $this->assertSame("le membre n'a pas la fonction CP", $block->describe(['fonctions' => 'CP'], true));
        $this->assertSame('le membre a la fonction', $block->label());
        $this->assertSame("le membre n'a pas la fonction", $block->negatedLabel());
    }

    public function test_groupe_types(): void
    {
        $membre = $this->membre(1);
        $this->attribute($membre, $this->groupe(1, $this->groupeType(7)), $this->fonction(1));
        $block = new MembreGroupeTypesBlock();

        $this->assertTrue($block->matches($membre, ['types' => [7]]));
        $this->assertFalse($block->matches($membre, ['types' => [8]]));
        $this->assertStringContainsString('Branche', $block->describe(['types' => 'Branche'], false));
        $this->assertNotNull($block->negatedLabel());
    }

    public function test_attribution_groupes_describe(): void
    {
        $block = new MembreAttributionGroupesBlock();
        $choices = $block->params()['sous_groupes']->choices;
        $this->assertStringEndsWith('Someo ' . $choices['tous'], $block->describe(['groupes' => 'Someo', 'sous_groupes' => $choices['tous']], false));
        // « sans sous-groupes » is the plain case: the sentence leaves it out
        $this->assertStringEndsWith(' ADABS', $block->describe(['groupes' => 'ADABS', 'sous_groupes' => $choices['aucun']], true));
    }

    public function test_context(): void
    {
        $context = new MembreContext();
        $membre  = $this->membre(1);
        $this->assertSame('membre', $context->id());
        $this->assertSame(['membre' => $membre], $context->expressionValues($membre));
        $this->assertArrayHasKey('membre', $context->variables());
    }
}
