<?php

namespace NetBS\CoreBundle\Rule;

use Psr\Log\LoggerInterface;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Evaluates rules in PHP. matches(): anything broken counts as "no match" and is logged, never thrown.
 * matchesStrict(): for callers where "no match" would be wrong (the LDAP directory), throws instead.
 */
final class RuleEvaluator
{
    private ExpressionLanguage $language;

    public function __construct(
        private RuleRegistry $registry,
        private LoggerInterface $logger,
    ) {
        $this->language = new ExpressionLanguage();
    }

    public function matches(Rule|string|null $rule, string $contextId, object $subject): bool
    {
        try {
            $parsed = $rule instanceof Rule ? $rule : Rule::fromJson($rule);
            if ($parsed->isEmpty()) {
                return false;
            }

            return $this->matchesStrict($parsed, $contextId, $subject);
        } catch (\Throwable $e) {
            $this->logger->warning('Rule evaluation failed, counted as no match: {message}', [
                'message' => ($e->getPrevious() ?? $e)->getMessage(),
                'context' => $contextId,
                'rule'    => $rule instanceof Rule ? $rule->toJson() : $rule,
            ]);

            return false;
        }
    }

    /** @throws RuleEvaluationException when the rule is empty, broken, or fails for this subject */
    public function matchesStrict(Rule|string|null $rule, string $contextId, object $subject): bool
    {
        try {
            $rule = $rule instanceof Rule ? $rule : Rule::fromJson($rule);
            if ($rule->isEmpty()) {
                throw new InvalidRuleException('Empty rule.');
            }

            if ($rule->mode === Rule::EXPERT) {
                $values = $this->registry->context($contextId)->expressionValues($subject);

                return (bool) $this->language->evaluate($rule->expression, $values);
            }

            return $this->group($rule->root, $subject);
        } catch (\Throwable $e) {
            throw new RuleEvaluationException($e->getMessage(), 0, $e);
        }
    }

    private function group(RuleGroup $group, object $subject): bool
    {
        foreach ($group->items as $item) {
            $result = $item instanceof RuleGroup ? $this->group($item, $subject) : $this->condition($item, $subject);
            if ($group->op === 'and' && !$result) {
                return false;
            }
            if ($group->op === 'or' && $result) {
                return true;
            }
        }

        return $group->op === 'and';
    }

    private function condition(RuleCondition $condition, object $subject): bool
    {
        $block = $this->registry->block($condition->block)
            ?? throw new InvalidRuleException(sprintf('Unknown rule block "%s".', $condition->block));

        return $block->matches($subject, $condition->params) !== $condition->not;
    }
}
