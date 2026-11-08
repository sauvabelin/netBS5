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

    private function one(RuleCondition $condition): string
    {
        return $this->describer->describe(Rule::blocks(new RuleGroup('and', [$condition])), 'test');
    }

    public function test_nested_group_is_parenthesised_and_joined(): void
    {
        $n       = new RuleCondition('test.n', ['op' => 'ge', 'value' => 3]);
        $flag    = new RuleCondition('test.flag', []);
        $notFlag = new RuleCondition('test.flag', [], true);

        $sentence = $this->describer->describe(Rule::blocks(new RuleGroup('or', [
            new RuleGroup('and', [$n, $flag]),
            $notFlag,
        ])), 'test');

        $this->assertStringStartsWith('(' . $this->one($n), $sentence);
        $this->assertStringContainsString($this->one($flag) . ')', $sentence);
        $this->assertStringEndsWith($this->one($notFlag), $sentence);
        $this->assertSame(1, substr_count($sentence, '('));
        $this->assertNotSame($this->one($flag), $this->one($notFlag));
    }

    public function test_and_and_or_use_distinct_joiners(): void
    {
        $items = [new RuleCondition('test.n', ['op' => 'ge', 'value' => 3]), new RuleCondition('test.flag', [])];
        $and   = $this->describer->describe(Rule::blocks(new RuleGroup('and', $items)), 'test');
        $or    = $this->describer->describe(Rule::blocks(new RuleGroup('or', $items)), 'test');

        $this->assertNotSame($and, $or);
        $this->assertStringNotContainsString('(', $and);
    }

    public function test_single_item_nested_group_is_not_parenthesised(): void
    {
        $flag = new RuleCondition('test.flag', []);
        $rule = Rule::blocks(new RuleGroup('and', [new RuleGroup('or', [$flag])]));

        $this->assertSame($this->one($flag), $this->describer->describe($rule, 'test'));
    }

    public function test_empty_forms_describe_alike(): void
    {
        $empty = $this->describer->describe(null, 'test');
        $this->assertSame($empty, $this->describer->describe(Rule::empty(), 'test'));
        $this->assertSame($empty, $this->describer->describe(Rule::expert(''), 'test'));
    }

    public function test_expert(): void
    {
        $this->assertStringEndsWith('n > 3', $this->describer->describe(Rule::expert('n > 3'), 'test'));
    }

    public function test_invalid_differs_from_empty(): void
    {
        $this->assertNotSame($this->describer->describe(null, 'test'), $this->describer->describe('{', 'test'));
    }

    public function test_unknown_block_id_appears(): void
    {
        $this->assertStringContainsString('nope.block', $this->one(new RuleCondition('nope.block', [])));
    }

    public function test_deleted_reference_id_appears(): void
    {
        $this->assertStringContainsString('999999999', $this->one(new RuleCondition('test.groupes', ['groupes' => [999999999]])));
    }
}
