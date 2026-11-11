<?php

declare(strict_types=1);

namespace App\Tests\FichierBundle\Rule;

use App\Tests\Support\InMemoryFichierTrait;
use NetBS\CoreBundle\Rule\RuleRegistry;
use NetBS\FichierBundle\Entity\GroupeCategorie;
use NetBS\FichierBundle\Rule\Block\GroupeAmongBlock;
use NetBS\FichierBundle\Rule\Block\GroupeCategoriesBlock;
use NetBS\FichierBundle\Rule\Block\GroupeTypesBlock;
use NetBS\FichierBundle\Rule\Block\GroupeUnderBlock;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GroupeBlocksTest extends KernelTestCase
{
    use InMemoryFichierTrait;

    public function testTypes(): void
    {
        $groupe = $this->groupe(1, $this->groupeType(7));
        $block  = new GroupeTypesBlock();

        $this->assertTrue($block->matches($groupe, ['types' => [7]]));
        $this->assertFalse($block->matches($groupe, ['types' => [8]]));
        $this->assertFalse($block->matches($this->groupe(2), ['types' => [7]]), 'no type');
    }

    public function testCategories(): void
    {
        $categorie = new GroupeCategorie();
        $this->setEntityId($categorie, 4);
        $type = $this->groupeType(7);
        $type->setGroupeCategorie($categorie);
        $block = new GroupeCategoriesBlock();

        $this->assertTrue($block->matches($this->groupe(1, $type), ['categories' => [4]]));
        $this->assertFalse($block->matches($this->groupe(1, $type), ['categories' => [5]]));
        $this->assertFalse($block->matches($this->groupe(2, $this->groupeType(8)), ['categories' => [4]]), 'type without catégorie');
    }

    public function testParmi(): void
    {
        $block = new GroupeAmongBlock();

        $this->assertTrue($block->matches($this->groupe(3), ['groupes' => [2, 3]]));
        $this->assertFalse($block->matches($this->groupe(3), ['groupes' => [2]]));
    }

    public function testSousMatchesAnyAncestorNotSelf(): void
    {
        $root       = $this->groupe(1);
        $child      = $this->groupe(2, null, $root);
        $grandchild = $this->groupe(3, null, $child);
        $block      = new GroupeUnderBlock();

        $this->assertTrue($block->matches($grandchild, ['groupes' => [1]]));
        $this->assertTrue($block->matches($grandchild, ['groupes' => [2]]));
        $this->assertFalse($block->matches($root, ['groupes' => [1]]), 'not itself');
        $this->assertFalse($block->matches($child, ['groupes' => [3]]), 'not a descendant');
    }

    public function testSousTerminatesOnParentCycle(): void
    {
        $a = $this->groupe(1);
        $b = $this->groupe(2, null, $a);
        $a->setParent($b);

        $this->assertFalse((new GroupeUnderBlock())->matches($a, ['groupes' => [9]]));
    }

    public function testContextRegistered(): void
    {
        $registry = static::getContainer()->get(RuleRegistry::class);

        foreach (['groupe.types', 'groupe.categories', 'groupe.parmi', 'groupe.sous'] as $id) {
            $this->assertSame(['groupe'], $registry->block($id)?->contexts(), $id);
        }
    }
}
