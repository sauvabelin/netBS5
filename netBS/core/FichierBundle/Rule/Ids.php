<?php

namespace NetBS\FichierBundle\Rule;

/** Compares entities against the row ids stored in rule parameters. */
final class Ids
{
    public static function contains(mixed $ids, ?object $entity): bool
    {
        return $entity !== null && is_array($ids) && in_array((int) $entity->getId(), array_map('intval', $ids), true);
    }
}
