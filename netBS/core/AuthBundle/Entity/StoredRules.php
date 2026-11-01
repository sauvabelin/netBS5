<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Entity;

use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\InvalidRuleException;
use NetBS\CoreBundle\Rule\Rule;

/** The owned-rule bookkeeping both directory entities share. */
final class StoredRules
{
    /**
     * The row after setting $json on it: updated in place, created, or (empty
     * rule, unless $keepEmpty) removed.
     */
    public static function update(?StoredRule $row, string $context, ?string $json, bool $keepEmpty): ?StoredRule
    {
        if (!$keepEmpty && ($json === null || self::emptyJson($json))) {
            return null;
        }
        if ($row === null) {
            return new StoredRule($context, $json);
        }
        $row->setDefinition($json);

        return $row;
    }

    /** No row, an empty rule, or one that doesn't parse. */
    public static function isEmpty(?StoredRule $row): bool
    {
        return $row === null || self::emptyJson($row->getDefinition());
    }

    private static function emptyJson(string $json): bool
    {
        try {
            return Rule::fromJson($json)->isEmpty();
        } catch (InvalidRuleException) {
            return true;
        }
    }
}
