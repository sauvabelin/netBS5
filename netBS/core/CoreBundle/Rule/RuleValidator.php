<?php

namespace NetBS\CoreBundle\Rule;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/** Checks a rule's structure, parameters, references and expert syntax; returns French messages. */
final class RuleValidator
{
    private ExpressionLanguage $language;

    public function __construct(
        private RuleRegistry $registry,
        private EntityTargets $targets,
    ) {
        $this->language = new ExpressionLanguage();
    }

    /** @return list<string> */
    public function validate(Rule|string|null $rule, string $contextId, bool $allowEmpty = true): array
    {
        try {
            $rule = $rule instanceof Rule ? $rule : Rule::fromJson($rule);
        } catch (InvalidRuleException) {
            return ['Règle invalide.'];
        }

        if ($rule->isEmpty()) {
            return $allowEmpty ? [] : ['Ajoutez au moins une condition.'];
        }

        if ($rule->mode === Rule::EXPERT) {
            try {
                $this->language->lint($rule->expression, array_keys($this->registry->context($contextId)->variables()));
            } catch (SyntaxError $e) {
                return ['Expression invalide : ' . $e->getMessage()];
            }

            return [];
        }

        $errors = [];
        foreach ($rule->root->items as $item) {
            if ($item instanceof RuleGroup) {
                if ($item->items === []) {
                    $errors[] = 'Ensemble de conditions vide.';
                }
                foreach ($item->items as $nested) {
                    if ($nested instanceof RuleGroup) {
                        $errors[] = "Trop de niveaux d'ensembles imbriqués.";
                    }
                }
            }
        }

        $references = [];
        foreach ($rule->conditions() as $condition) {
            array_push($errors, ...$this->condition($condition, $contextId, $references));
        }

        foreach ($references as $target => $ids) {
            $live = $this->targets->labels($target, $ids);
            foreach (array_unique($ids) as $id) {
                if (!isset($live[$id])) {
                    $errors[] = "Référence supprimée (id $id).";
                }
            }
        }

        return array_values(array_unique($errors));
    }

    /** @param array<string,list<int>> $references collects entity ids by target */
    private function condition(RuleCondition $condition, string $contextId, array &$references): array
    {
        $block = $this->registry->block($condition->block);
        if ($block === null) {
            return ["Condition inconnue : {$condition->block}."];
        }
        if (!in_array($contextId, $block->contexts(), true)) {
            return ["Condition « {$block->label()} » non disponible ici."];
        }

        $errors = [];
        foreach ($block->params() as $name => $param) {
            $value = $condition->params[$name] ?? null;
            if ($value === null || $value === '' || $value === []) {
                $errors[] = "Paramètre manquant dans « {$block->label()} » ($name).";
                continue;
            }

            $valid = match ($param->kind) {
                RuleParam::OPERATOR, RuleParam::CHOICE => is_string($value) && array_key_exists($value, $param->choices),
                RuleParam::INT => $this->validInt($value, $param),
                RuleParam::ENTITIES => is_array($value) && array_is_list($value)
                    && array_filter($value, fn ($id) => !is_int($id) && !(is_string($id) && ctype_digit($id))) === [],
                default => false,
            };

            if (!$valid) {
                $errors[] = "Valeur non autorisée dans « {$block->label()} » ($name).";
            } elseif ($param->kind === RuleParam::ENTITIES) {
                $references[$param->target] = array_merge($references[$param->target] ?? [], array_map('intval', $value));
            }
        }

        return $errors;
    }

    private function validInt(mixed $value, RuleParam $param): bool
    {
        if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', $value))) {
            return false;
        }
        $value = (int) $value;

        return ($param->min === null || $value >= $param->min) && ($param->max === null || $value <= $param->max);
    }
}
