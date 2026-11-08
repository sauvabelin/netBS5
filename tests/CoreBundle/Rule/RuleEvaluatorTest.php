<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Rule;

use App\Tests\Support\CollectingLogger;
use App\Tests\Support\TestFlagBlock;
use App\Tests\Support\TestNBlock;
use App\Tests\Support\TestRuleContext;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleEvaluationException;
use NetBS\CoreBundle\Rule\RuleEvaluator;
use NetBS\CoreBundle\Rule\RuleGroup;
use NetBS\CoreBundle\Rule\RuleRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class RuleEvaluatorTest extends TestCase
{
    private CollectingLogger $logger;
    private RuleEvaluator $evaluator;
    private \ArrayObject $subject;

    protected function setUp(): void
    {
        $this->logger    = new CollectingLogger();
        $registry        = new RuleRegistry([new TestRuleContext()], [new TestNBlock(), new TestFlagBlock()]);
        $this->evaluator = new RuleEvaluator($registry, $this->logger);
        $this->subject   = new \ArrayObject(['n' => 5, 'flag' => false]);
    }

    private function n(string $op, int $value, bool $not = false): RuleCondition
    {
        return new RuleCondition('test.n', ['op' => $op, 'value' => $value], $not);
    }

    private function eval(Rule|string|null $rule): bool
    {
        return $this->evaluator->matches($rule, 'test', $this->subject);
    }

    public function test_and_or_not(): void
    {
        $flag = new RuleCondition('test.flag', []);
        $this->assertTrue($this->eval(Rule::blocks(new RuleGroup('and', [$this->n('ge', 3), $this->n('lt', 6)]))));
        $this->assertFalse($this->eval(Rule::blocks(new RuleGroup('and', [$this->n('ge', 3), $flag]))));
        $this->assertTrue($this->eval(Rule::blocks(new RuleGroup('or', [$this->n('ge', 3), $flag]))));
        $this->assertFalse($this->eval(Rule::blocks(new RuleGroup('or', [$this->n('gt', 5), $flag]))));
        $this->assertTrue($this->eval(Rule::blocks(new RuleGroup('and', [new RuleCondition('test.flag', [], true)]))));
        $this->assertFalse($this->eval(Rule::blocks(new RuleGroup('and', [$this->n('eq', 5, true)]))));
    }

    public function test_nested_group(): void
    {
        $rule = Rule::blocks(new RuleGroup('or', [
            new RuleCondition('test.flag', []),
            new RuleGroup('and', [$this->n('ge', 3), $this->n('ne', 4)]),
        ]));
        $this->assertTrue($this->eval($rule));
        $this->assertTrue($this->eval($rule->toJson()));
    }

    public function test_empty_rule_is_false(): void
    {
        $this->assertFalse($this->eval(null));
        $this->assertFalse($this->eval(Rule::empty()));
        $this->assertFalse($this->eval(Rule::expert('')));
        $this->assertSame(0, $this->logger->count(LogLevel::WARNING), 'empty rule is not a warning');
    }

    public function test_expert_expression(): void
    {
        $this->assertTrue($this->eval(Rule::expert('n > 3')));
        $this->assertFalse($this->eval(Rule::expert('n > 7')));
    }

    public function test_expert_exception_is_false_and_logged(): void
    {
        $this->assertFalse($this->eval(Rule::expert('n.foo()')));
        $this->assertSame(1, $this->logger->count(LogLevel::WARNING));
    }

    public function test_malformed_json_is_false_and_logged(): void
    {
        $this->assertFalse($this->eval('{nope'));
        $this->assertSame(1, $this->logger->count(LogLevel::WARNING));
    }

    public function test_unknown_block_is_false_and_logged(): void
    {
        $this->assertFalse($this->eval(Rule::blocks(new RuleGroup('or', [new RuleCondition('nope', [])]))));
        $this->assertSame(1, $this->logger->count(LogLevel::WARNING));
    }

    // matchesStrict(): the directory's path, where a broken rule must not count as "no match".

    private function strict(Rule|string|null $rule): bool
    {
        return $this->evaluator->matchesStrict($rule, 'test', $this->subject);
    }

    public function test_strict_throws_on_unknown_block(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->strict(Rule::blocks(new RuleGroup('or', [new RuleCondition('nope', [])])));
    }

    public function test_strict_throws_on_expert_runtime_error(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->strict(Rule::expert('n.foo()'));
    }

    public function test_strict_throws_on_malformed_json(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->strict('{nope');
    }

    /** @dataProvider emptyRules */
    public function test_strict_throws_on_empty_rule(Rule|string|null $rule): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->strict($rule);
    }

    public static function emptyRules(): array
    {
        return [[null], ['{}'], [Rule::empty()], [Rule::expert('')]];
    }
}
