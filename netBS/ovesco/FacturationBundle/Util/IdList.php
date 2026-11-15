<?php

namespace Ovesco\FacturationBundle\Util;

/**
 * Id lists that round-trip through hidden form fields. JSON, never PHP
 * serialize: the posted value is user input and unserialize() would
 * instantiate arbitrary objects.
 */
final class IdList
{
    public static function encode(array $ids): string
    {
        return json_encode(array_values(array_map('intval', array_filter($ids, 'is_numeric'))));
    }

    /** @return int[] */
    public static function decode(?string $value): array
    {
        $ids = json_decode((string) $value, true);
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_map('intval', array_filter($ids, fn($id) => is_int($id) || (is_string($id) && ctype_digit($id)))));
    }
}
