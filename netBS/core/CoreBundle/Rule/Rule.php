<?php

namespace NetBS\CoreBundle\Rule;

/**
 * A condition built from blocks (a root group) or written as an expert expression.
 * Serialized as {"mode":"blocks","op":"and","items":[...]} or {"mode":"expert","expression":"..."}.
 */
final class Rule
{
    const BLOCKS = 'blocks';
    const EXPERT = 'expert';

    private function __construct(
        public readonly string $mode,
        public readonly ?RuleGroup $root,
        public readonly ?string $expression,
    ) {
    }

    public static function blocks(RuleGroup $root): self
    {
        return new self(self::BLOCKS, $root, null);
    }

    public static function expert(string $expression): self
    {
        return new self(self::EXPERT, null, $expression);
    }

    public static function empty(): self
    {
        return self::blocks(new RuleGroup('and', []));
    }

    public static function fromJson(?string $json): self
    {
        if ($json === null || trim($json) === '') {
            return self::empty();
        }

        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidRuleException('Malformed rule JSON: ' . $e->getMessage(), 0, $e);
        }

        if (!is_array($data)) {
            throw new InvalidRuleException('Rule JSON must be an object.');
        }

        return match ($data['mode'] ?? null) {
            self::BLOCKS => self::blocks(self::groupFromArray($data)),
            self::EXPERT => is_string($data['expression'] ?? null)
                ? self::expert($data['expression'])
                : throw new InvalidRuleException('Expert rule without expression.'),
            default      => throw new InvalidRuleException('Unknown rule mode.'),
        };
    }

    private static function groupFromArray(array $data): RuleGroup
    {
        if (!is_string($data['op'] ?? null) || !is_array($data['items'] ?? null) || !array_is_list($data['items'])) {
            throw new InvalidRuleException('A group needs "op" and a list of "items".');
        }

        $items = [];
        foreach ($data['items'] as $item) {
            if (!is_array($item)) {
                throw new InvalidRuleException('A group item must be an object.');
            }
            if (array_key_exists('items', $item)) {
                $items[] = self::groupFromArray($item);
                continue;
            }
            if (!is_string($item['block'] ?? null) || !is_array($item['params'] ?? [])) {
                throw new InvalidRuleException('A condition needs a "block" and object "params".');
            }
            $items[] = new RuleCondition($item['block'], $item['params'] ?? [], (bool) ($item['not'] ?? false));
        }

        return new RuleGroup($data['op'], $items);
    }

    public function toJson(): string
    {
        $data = $this->mode === self::EXPERT
            ? ['mode' => self::EXPERT, 'expression' => $this->expression]
            : ['mode' => self::BLOCKS] + self::groupToArray($this->root);

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function groupToArray(RuleGroup $group): array
    {
        return [
            'op'    => $group->op,
            'items' => array_map(fn ($item) => $item instanceof RuleGroup
                ? self::groupToArray($item)
                : ['block' => $item->block, 'not' => $item->not, 'params' => (object) $item->params], $group->items),
        ];
    }

    public function isEmpty(): bool
    {
        return $this->mode === self::EXPERT
            ? trim((string) $this->expression) === ''
            : $this->conditions() === [];
    }

    /** @return list<RuleCondition> */
    public function conditions(): array
    {
        if ($this->root === null) {
            return [];
        }

        $conditions = [];
        $walk = function (RuleGroup $group) use (&$walk, &$conditions) {
            foreach ($group->items as $item) {
                $item instanceof RuleGroup ? $walk($item) : $conditions[] = $item;
            }
        };
        $walk($this->root);

        return $conditions;
    }
}
