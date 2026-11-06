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

/** matchesStrict(): the directory's path, where a broken rule must not count as "no match". */
final class RuleEvaluatorStrictTest extends TestCase
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

    private function strict(Rule|string|null $rule): bool
    {
        return $this->evaluator->matchesStrict($rule, 'test', $this->subject);
    }

    public function testStrictMatchesLikeLenient(): void
    {
        $match   = Rule::blocks(new RuleGroup('and', [new RuleCondition('test.n', ['op' => 'ge', 'value' => 3])]));
        $noMatch = Rule::expert('n > 7');

        $this->assertTrue($this->strict($match));
        $this->assertTrue($this->strict($match->toJson()));
        $this->assertFalse($this->strict($noMatch));
    }

    public function testStrictThrowsOnUnknownBlock(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->strict(Rule::blocks(new RuleGroup('or', [new RuleCondition('nope', [])])));
    }

    public function testStrictThrowsOnExpertRuntimeError(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->strict(Rule::expert('n.foo()'));
    }

    public function testStrictThrowsOnMalformedJson(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->strict('{nope');
    }

    /** @dataProvider emptyRules */
    public function testStrictThrowsOnEmptyRule(Rule|string|null $rule): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->strict($rule);
    }

    public static function emptyRules(): array
    {
        return [[null], ['{}'], [Rule::empty()], [Rule::expert('')]];
    }

    public function testLenientStillReturnsFalse(): void
    {
        $this->assertFalse($this->evaluator->matches(Rule::expert('n.foo()'), 'test', $this->subject));
        $this->assertFalse($this->evaluator->matches('{nope', 'test', $this->subject));
        $this->assertFalse($this->evaluator->matches(null, 'test', $this->subject));
        $this->assertSame(2, $this->logger->count(LogLevel::WARNING), 'empty rule is not a warning');
    }
}
