<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Rule;

use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleGlossary;
use NetBS\CoreBundle\Rule\RuleRegistry;
use NetBS\CoreBundle\Rule\RuleValidator;
use NetBS\FichierBundle\Rule\MembreContext;
use Ovesco\FacturationBundle\Rule\FactureContext;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RuleGlossaryTest extends KernelTestCase
{
    private function entry(array $glossary, string $name): array
    {
        foreach ($glossary as $entry) {
            if ($entry['name'] === $name) {
                return $entry;
            }
        }
        $this->fail("No glossary entry $name");
    }

    public function test_methods_are_read_from_the_variable_class(): void
    {
        $membre = $this->entry((new RuleGlossary())->variables(new MembreContext()), 'membre');

        $this->assertSame('Membre', $membre['types'][0]['name']);
        $methods = $membre['types'][0]['methods'];
        $this->assertContains('getPrenom()', $methods);
        $this->assertContains('getActivesAttributions()', $methods);
        // setters and methods that need an argument can't be used in an expression
        $this->assertNotContains('setPrenom()', $methods);
        $this->assertNotContains('hasFonction()', $methods);
        $this->assertSame($methods, array_values(array_unique($methods)));
    }

    public function test_union_and_scalar_types(): void
    {
        $glossary = (new RuleGlossary())->variables(new FactureContext());

        $this->assertSame(['Membre', 'Famille'], array_column($this->entry($glossary, 'debiteur')['types'], 'name'));
        $scalar = $this->entry($glossary, 'isFamille')['types'];
        $this->assertCount(1, $scalar);
        $this->assertSame([], $scalar[0]['methods']);
        $this->assertNotSame('', $this->entry($glossary, 'facture')['description']);
    }

    public function test_every_context_example_is_a_valid_expert_rule(): void
    {
        self::bootKernel();
        $registry  = self::getContainer()->get(RuleRegistry::class);
        $validator = self::getContainer()->get(RuleValidator::class);

        foreach (['membre', 'facture', 'attribution_sous_groupe'] as $id) {
            $example = $registry->context($id)->example();
            $this->assertNotSame('', $example);
            $this->assertSame([], $validator->validate(Rule::expert($example)->toJson(), $id), $id);
        }
    }
}
