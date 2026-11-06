<?php

namespace NetBS\CoreBundle\Rule;

/** Turns a rule into a plain-French sentence. */
final class RuleDescriber
{
    public function __construct(
        private RuleRegistry $registry,
        private EntityTargets $targets,
    ) {
    }

    public function describe(Rule|string|null $rule, string $contextId): string
    {
        try {
            $rule = $rule instanceof Rule ? $rule : Rule::fromJson($rule);
        } catch (InvalidRuleException) {
            return 'Règle invalide';
        }

        if ($rule->isEmpty()) {
            return 'Aucune condition';
        }
        if ($rule->mode === Rule::EXPERT) {
            return 'Expression avancée : ' . $rule->expression;
        }

        return $this->group($rule->root, $this->entityLabels($rule), false);
    }

    private function group(RuleGroup $group, array $labels, bool $nested): string
    {
        $parts = array_map(fn ($item) => $item instanceof RuleGroup
            ? $this->group($item, $labels, true)
            : $this->condition($item, $labels), $group->items);

        $sentence = implode($group->op === 'and' ? ' et ' : ' ou ', $parts);

        return $nested && count($parts) > 1 ? "($sentence)" : $sentence;
    }

    private function condition(RuleCondition $condition, array $labels): string
    {
        $block = $this->registry->block($condition->block);
        if ($block === null) {
            return sprintf('condition supprimée ou inconnue (%s)', $condition->block);
        }

        $display = [];
        foreach ($block->params() as $name => $param) {
            $value = $condition->params[$name] ?? null;
            $display[$name] = match ($param->kind) {
                RuleParam::OPERATOR, RuleParam::CHOICE => (string) ($param->choices[$value] ?? $value),
                RuleParam::ENTITIES => implode(', ', array_map(
                    fn ($id) => $labels[$param->target][(int) $id] ?? "#$id (supprimé)",
                    is_array($value) ? $value : [],
                )),
                default => (string) $value,
            };
        }

        return $block->describe($display, $condition->not);
    }

    /** @return array<string,array<int,string>> target => id => label, one query per target */
    private function entityLabels(Rule $rule): array
    {
        $ids = [];
        foreach ($rule->conditions() as $condition) {
            foreach ($this->registry->block($condition->block)?->params() ?? [] as $name => $param) {
                if ($param->kind === RuleParam::ENTITIES && is_array($condition->params[$name] ?? null)) {
                    $ids[$param->target] = array_merge($ids[$param->target] ?? [], $condition->params[$name]);
                }
            }
        }

        $labels = [];
        foreach ($ids as $target => $targetIds) {
            $labels[$target] = $this->targets->labels($target, $targetIds);
        }

        return $labels;
    }
}
