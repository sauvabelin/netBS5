<?php

namespace App\Settings;

use NetBS\CoreBundle\Service\ParameterManager;

/**
 * The only reader of the legacy `bs.*_id` parameters: row ids of groupes, fonctions, groupe types
 * and catégories, bound by hand on the Paramètres page (Legacy card). They are being replaced by
 * rules family by family (see TODO.local.md), so every read goes through here.
 *
 * id() never checks that the row still exists: an unset, malformed or stale id simply matches
 * nothing, so the feature using it does nothing.
 */
class BsReferences
{
    public function __construct(private readonly ParameterManager $params)
    {
    }

    /** @param string $key e.g. 'groupe.adabs_id' */
    public function id(string $key): ?int
    {
        $raw = trim((string) $this->params->getValue('bs', $key));

        return preg_match('/^\d+$/', $raw) && (int) $raw > 0 ? (int) $raw : null;
    }

    /** @return int[] the ids that are set, in order */
    public function ids(string ...$keys): array
    {
        return array_values(array_filter(array_map($this->id(...), $keys), fn (?int $id) => $id !== null));
    }
}
