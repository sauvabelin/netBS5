<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Rule;

use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleDescriber;
use NetBS\CoreBundle\Rule\RuleGroup;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RuleDescriberTest extends KernelTestCase
{
    private RuleDescriber $describer;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->describer = self::getContainer()->get(RuleDescriber::class);
    }

    public function test_sentence(): void
    {
        $rule = Rule::blocks(new RuleGroup('or', [
            new RuleGroup('and', [
                new RuleCondition('test.n', ['op' => 'ge', 'value' => 3]),
                new RuleCondition('test.flag', []),
            ]),
            new RuleCondition('test.flag', [], true),
        ]));

        $this->assertSame("(n ≥ 3 et est marqué) ou n'est pas marqué", $this->describer->describe($rule, 'test'));
    }

    public function test_empty(): void
    {
        $this->assertSame('Aucune condition', $this->describer->describe(null, 'test'));
    }

    public function test_expert(): void
    {
        $this->assertStringEndsWith('n > 3', $this->describer->describe(Rule::expert('n > 3'), 'test'));
    }

    public function test_invalid(): void
    {
        $this->assertSame('Règle invalide', $this->describer->describe('{', 'test'));
    }

    public function test_deleted_reference_is_marked(): void
    {
        $rule = Rule::blocks(new RuleGroup('and', [new RuleCondition('test.groupes', ['groupes' => [999999999]])]));
        $this->assertSame('est dans #999999999 (supprimé)', $this->describer->describe($rule, 'test'));
    }
}
