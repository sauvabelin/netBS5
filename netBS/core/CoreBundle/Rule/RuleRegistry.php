<?php

namespace NetBS\CoreBundle\Rule;

/** Every rule context and block contributed by the bundles. */
final class RuleRegistry
{
    /** @var array<string,RuleContext> */
    private array $contexts = [];

    /** @var array<string,RuleBlock> */
    private array $blocks = [];

    public function __construct(iterable $contexts, iterable $blocks)
    {
        foreach ($contexts as $context) {
            $this->contexts[$context->id()] = $context;
        }
        foreach ($blocks as $block) {
            if (isset($this->blocks[$block->id()])) {
                throw new \LogicException(sprintf('Duplicate rule block "%s".', $block->id()));
            }
            $this->blocks[$block->id()] = $block;
        }
    }

    public function context(string $id): RuleContext
    {
        return $this->contexts[$id] ?? throw new \LogicException(sprintf('Unknown rule context "%s".', $id));
    }

    /** @return array<string,RuleBlock> */
    public function blocksFor(string $contextId): array
    {
        return array_filter($this->blocks, fn (RuleBlock $b) => in_array($contextId, $b->contexts(), true));
    }

    public function block(string $id): ?RuleBlock
    {
        return $this->blocks[$id] ?? null;
    }
}
