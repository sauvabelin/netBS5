<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

use NetBS\AuthBundle\Rule\DirectoryRuleLabeler;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\RuleEvaluationException;
use NetBS\CoreBundle\Rule\RuleEvaluator;
use NetBS\CoreBundle\Rule\RuleValidator;

/**
 * The directory's strict view of its rules, for one projection run: a broken
 * rule (invalid, deleted reference, failing at run time) is reported, never
 * read as "no match". Validation results are cached per rule.
 */
final class DirectoryRuleChecker
{
    /** @var array<string, ?string> rule key => problem */
    private array $problems = [];
    /** @var array<string, mixed> see memo() */
    private array $memo = [];

    public function __construct(
        private readonly RuleValidator $validator,
        private readonly RuleEvaluator $evaluator,
        private readonly DirectoryRuleLabeler $labeler,
    ) {
    }

    /** The first validation error of $rule, or null when it's usable. */
    public function problem(StoredRule $rule): ?string
    {
        $key = self::key($rule);
        if (!array_key_exists($key, $this->problems)) {
            $this->problems[$key] = $this->validator->validate($rule->getDefinition(), $rule->getContext(), false)[0] ?? null;
        }

        return $this->problems[$key];
    }

    /** @throws RuleEvaluationException */
    public function matches(StoredRule $rule, object $subject): bool
    {
        return $this->evaluator->matchesStrict($rule->getDefinition(), $rule->getContext(), $subject);
    }

    /** « {owner} : {message} »; the owner is looked up once per rule. */
    public function reason(StoredRule $rule, string $message): string
    {
        $owner = $this->memo('owner:' . self::key($rule), fn () => $this->labeler->label($rule) ?? sprintf('Règle #%d', $rule->getId()));

        return sprintf('%s : %s', $owner, $message);
    }

    /** $compute's result, computed once per $key for this run. */
    public function memo(string $key, callable $compute): mixed
    {
        if (!array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $compute();
        }

        return $this->memo[$key];
    }

    private static function key(StoredRule $rule): string
    {
        return $rule->getId() !== null ? 'id:' . $rule->getId() : 'obj:' . spl_object_id($rule);
    }
}
