<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Rule;

use NetBS\CoreBundle\Rule\InvalidRuleException;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use PHPUnit\Framework\TestCase;

final class RuleTest extends TestCase
{
    public function test_round_trips_json(): void
    {
        $rule = Rule::blocks(new RuleGroup('or', [
            new RuleCondition('test.flag', [], true),
            new RuleGroup('and', [new RuleCondition('test.n', ['op' => 'ge', 'value' => 3])]),
        ]));

        $this->assertEquals($rule, Rule::fromJson($rule->toJson()));
        $this->assertEquals(Rule::expert('n > 3'), Rule::fromJson(Rule::expert('n > 3')->toJson()));
        $this->assertCount(2, $rule->conditions());
    }

    public function test_null_and_blank_are_empty(): void
    {
        $this->assertTrue(Rule::fromJson(null)->isEmpty());
        $this->assertTrue(Rule::fromJson('')->isEmpty());
        $this->assertTrue(Rule::expert('  ')->isEmpty());
        $this->assertTrue(Rule::empty()->isEmpty());
        $this->assertFalse(Rule::expert('n > 1')->isEmpty());
    }

    public function test_malformed_json_throws(): void
    {
        $bad = [
            '{',
            '{"mode":"x"}',
            '{"mode":"blocks","op":"xor","items":[]}',
            '{"mode":"blocks","op":"and","items":[{"params":{}}]}',
            '{"mode":"expert"}',
            '"text"',
        ];
        foreach ($bad as $json) {
            try {
                Rule::fromJson($json);
                $this->fail("$json must be rejected");
            } catch (InvalidRuleException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
